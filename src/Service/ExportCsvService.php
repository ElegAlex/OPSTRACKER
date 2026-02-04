<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Repository\OperationRepository;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service d'export CSV pour OpsTracker.
 *
 * Permet d'exporter les operations d'une campagne au format CSV.
 */
class ExportCsvService
{
    // Colonnes systeme exportees par defaut
    // RG-015 : Les donnees metier (matricule, nom) viennent de donneesPersonnalisees
    public const DEFAULT_COLUMNS = [
        'identifiant' => 'Identifiant',
        'description' => 'Description',
        'statut' => 'Statut',
        'segment' => 'Segment',
        'technicien' => 'Technicien',
        'date_planifiee' => 'Date planifiee',
        'date_realisation' => 'Date realisation',
        'notes' => 'Notes',
    ];

    public function __construct(
        private readonly OperationRepository $operationRepository,
    ) {
    }

    /**
     * Exporte les operations d'une campagne en CSV.
     *
     * @param array<string>|null $columns Colonnes a exporter (null = toutes)
     * @param array<string, mixed> $filters Filtres optionnels
     */
    public function exportCampagne(
        Campagne $campagne,
        ?array $columns = null,
        array $filters = []
    ): StreamedResponse {
        $columns = $columns ?? array_keys(self::DEFAULT_COLUMNS);

        $response = new StreamedResponse(function () use ($campagne, $columns, $filters) {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Impossible d\'ouvrir le flux de sortie.');
            }
            $csv = Writer::createFromStream($stream);
            $csv->setDelimiter(';');

            // En-tetes
            $headers = array_map(fn($col) => self::DEFAULT_COLUMNS[$col] ?? $col, $columns);
            $csv->insertOne($headers);

            // Recuperer les operations
            $operations = $this->getFilteredOperations($campagne, $filters);

            // Donnees
            foreach ($operations as $operation) {
                $row = $this->operationToRow($operation, $columns);
                $csv->insertOne($row);
            }
        });

        $filename = sprintf(
            'export_%s_%s.csv',
            $this->slugify($campagne->getNom() ?? 'campagne'),
            date('Y-m-d_His')
        );

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * Exporte toutes les operations d'un ensemble de campagnes.
     *
     * @param array<Campagne> $campagnes
     */
    public function exportMultipleCampagnes(array $campagnes): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($campagnes) {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Impossible d\'ouvrir le flux de sortie.');
            }
            $csv = Writer::createFromStream($stream);
            $csv->setDelimiter(';');

            // En-tetes avec campagne
            $headers = ['Campagne', ...array_values(self::DEFAULT_COLUMNS)];
            $csv->insertOne($headers);

            foreach ($campagnes as $campagne) {
                foreach ($campagne->getOperations() as $operation) {
                    $row = [$campagne->getNom(), ...$this->operationToRow($operation, array_keys(self::DEFAULT_COLUMNS))];
                    $csv->insertOne($row);
                }
            }
        });

        $filename = sprintf('export_operations_%s.csv', date('Y-m-d_His'));

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * Convertit une operation en ligne CSV.
     *
     * @param array<string> $columns
     * @return array<string>
     */
    private function operationToRow(Operation $operation, array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $row[] = match ($column) {
                'identifiant' => $operation->getDisplayIdentifier() ?? '',
                'description' => $operation->getDisplayName() ?? '',
                'statut' => $operation->getStatutLabel(),
                'segment' => $operation->getSegment()?->getNom() ?? '',
                'technicien' => $operation->getTechnicienAssigne()?->getNomComplet() ?? '',
                'date_planifiee' => $operation->getDatePlanifiee()?->format('d/m/Y') ?? '',
                'date_realisation' => $operation->getDateRealisation()?->format('d/m/Y H:i') ?? '',
                'notes' => $operation->getNotes() ?? '',
                default => '',
            };
        }

        return $row;
    }

    /**
     * Recupere les operations filtrees d'une campagne.
     *
     * @param array<string, mixed> $filters
     * @return iterable<Operation>
     */
    private function getFilteredOperations(Campagne $campagne, array $filters): iterable
    {
        // Si pas de filtres, retourner toutes les operations
        if (empty($filters)) {
            return $campagne->getOperations();
        }

        // Construire la requete avec filtres
        $qb = $this->operationRepository->createQueryBuilder('o')
            ->where('o.campagne = :campagne')
            ->setParameter('campagne', $campagne);

        if (isset($filters['statut']) && $filters['statut']) {
            $qb->andWhere('o.statut = :statut')
                ->setParameter('statut', $filters['statut']);
        }

        if (isset($filters['segment']) && $filters['segment']) {
            $qb->andWhere('o.segment = :segment')
                ->setParameter('segment', $filters['segment']);
        }

        if (isset($filters['technicien']) && $filters['technicien']) {
            $qb->andWhere('o.technicienAssigne = :technicien')
                ->setParameter('technicien', $filters['technicien']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Genere un slug pour le nom de fichier.
     */
    private function slugify(string $text): string
    {
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        if ($transliterated === false) {
            $transliterated = $text;
        }
        $replaced = preg_replace('/[^a-z0-9]+/', '_', $transliterated);
        if ($replaced === null) {
            $replaced = $transliterated;
        }
        $trimmed = trim($replaced, '_');

        return $trimmed !== '' ? $trimmed : 'export';
    }
}
