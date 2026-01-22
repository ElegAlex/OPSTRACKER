<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\Segment;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use League\Csv\CharsetConverter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service d'import CSV pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-012 : Encodage UTF-8/ISO auto-detecte, separateur auto-detecte, max 100 000 lignes
 * - RG-013 : Fichier .csv uniquement accepte
 * - RG-014 : Nouvelles operations creees avec statut "A planifier"
 * - RG-092 : Lignes en erreur ignorees (skip), fichier log genere
 * - RG-093 : Segments auto-crees si colonne segment mappee
 */
class ImportCsvService
{
    public const MAX_LINES = 100000;
    public const ALLOWED_EXTENSIONS = ['csv'];
    public const ALLOWED_MIME_TYPES = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];

    // Champs mappables vers Operation
    public const MAPPABLE_FIELDS = [
        'matricule' => ['label' => 'Matricule', 'required' => true, 'type' => 'string'],
        'nom' => ['label' => 'Nom / Libellé', 'required' => true, 'type' => 'string'],
        'segment' => ['label' => 'Segment / Bâtiment', 'required' => false, 'type' => 'segment'],
        'notes' => ['label' => 'Notes', 'required' => false, 'type' => 'string'],
        'date_planifiee' => ['label' => 'Date planifiée', 'required' => false, 'type' => 'date'],
    ];

    // Separateurs a tester pour la detection auto
    private const SEPARATORS = [',', ';', "\t", '|'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Valide un fichier CSV avant import.
     * RG-013 : Fichier .csv uniquement
     *
     * @return array{valid: bool, error?: string}
     */
    public function validateFile(UploadedFile $file): array
    {
        // Verifier l'extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return [
                'valid' => false,
                'error' => sprintf('Extension non autorisée : .%s. Seuls les fichiers .csv sont acceptés.', $extension),
            ];
        }

        // Verifier le type MIME
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return [
                'valid' => false,
                'error' => sprintf('Type de fichier non autorisé : %s.', $mimeType),
            ];
        }

        // Verifier la taille (max 50 Mo)
        if ($file->getSize() > 50 * 1024 * 1024) {
            return [
                'valid' => false,
                'error' => 'Le fichier dépasse la taille maximale de 50 Mo.',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Detecte l'encodage d'un fichier CSV.
     * RG-012 : Encodage UTF-8/ISO auto-detecte
     */
    public function detectEncoding(string $filePath): string
    {
        $content = file_get_contents($filePath, false, null, 0, 8192);

        // Detecter BOM UTF-8
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }

        // Tester si c'est du UTF-8 valide
        if (mb_check_encoding($content, 'UTF-8') && !preg_match('/[\x80-\x9F]/', $content)) {
            return 'UTF-8';
        }

        // Sinon, supposer ISO-8859-1 (Windows-1252)
        return 'ISO-8859-1';
    }

    /**
     * Detecte le separateur d'un fichier CSV.
     * RG-012 : Separateur auto-detecte
     */
    public function detectSeparator(string $filePath, string $encoding = 'UTF-8'): string
    {
        $content = file_get_contents($filePath, false, null, 0, 8192);

        // Convertir si necessaire
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Compter les occurrences de chaque separateur sur les premieres lignes
        $lines = explode("\n", $content);
        $firstLines = array_slice($lines, 0, 5);

        $counts = [];
        foreach (self::SEPARATORS as $sep) {
            $counts[$sep] = 0;
            foreach ($firstLines as $line) {
                $counts[$sep] += substr_count($line, $sep);
            }
        }

        // Le separateur le plus frequent est probablement le bon
        arsort($counts);
        $detected = array_key_first($counts);

        return $counts[$detected] > 0 ? $detected : ';';
    }

    /**
     * Analyse un fichier CSV et retourne les en-tetes + apercu.
     *
     * @return array{
     *     headers: array<int, string>,
     *     preview: array<int, array<string, string>>,
     *     total_lines: int,
     *     encoding: string,
     *     separator: string
     * }
     */
    public function analyzeFile(string $filePath): array
    {
        $encoding = $this->detectEncoding($filePath);
        $separator = $this->detectSeparator($filePath, $encoding);

        $reader = Reader::createFromPath($filePath, 'r');
        $reader->setDelimiter($separator);

        // Convertir l'encodage si necessaire
        if ($encoding !== 'UTF-8') {
            CharsetConverter::addTo($reader, $encoding, 'UTF-8');
        }

        $reader->setHeaderOffset(0);

        $headers = $reader->getHeader();
        $records = $reader->getRecords();

        // Compter les lignes et creer un apercu
        $preview = [];
        $count = 0;
        foreach ($records as $record) {
            $count++;
            if ($count <= 5) {
                $preview[] = $record;
            }
            // RG-012 : Verifier limite 100k lignes
            if ($count > self::MAX_LINES) {
                break;
            }
        }

        return [
            'headers' => $headers,
            'preview' => $preview,
            'total_lines' => min($count, self::MAX_LINES),
            'encoding' => $encoding,
            'separator' => $separator,
        ];
    }

    /**
     * Suggere un mapping automatique des colonnes.
     *
     * @param array<int, string> $headers En-tetes du CSV
     * @return array<string, int|null> Mapping field => header_index
     */
    public function suggestMapping(array $headers): array
    {
        $mapping = [];
        $normalizedHeaders = array_map(fn($h) => $this->normalizeHeader($h), $headers);

        // Patterns pour chaque champ
        $patterns = [
            'matricule' => ['matricule', 'mat', 'id', 'identifiant', 'code', 'ref', 'reference', 'numero'],
            'nom' => ['nom', 'name', 'libelle', 'label', 'designation', 'intitule', 'agent', 'utilisateur'],
            'segment' => ['segment', 'batiment', 'building', 'etage', 'floor', 'site', 'localisation', 'service', 'departement'],
            'notes' => ['notes', 'note', 'commentaire', 'comment', 'remarque', 'observation', 'description'],
            'date_planifiee' => ['date', 'date_planifiee', 'date_prevue', 'planification', 'echeance'],
        ];

        foreach (self::MAPPABLE_FIELDS as $field => $config) {
            $mapping[$field] = null;

            foreach ($patterns[$field] ?? [] as $pattern) {
                foreach ($normalizedHeaders as $index => $header) {
                    if (str_contains($header, $pattern)) {
                        $mapping[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Importe les operations depuis un fichier CSV.
     * RG-092 : Lignes en erreur ignorees, log genere
     * RG-093 : Segments auto-crees si colonne mappee
     *
     * @param array<string, int|null> $mapping Mapping field => header_index
     * @param array<string, string> $customFieldsMapping Mapping pour champs personnalises
     * @return ImportResult
     */
    public function import(
        Campagne $campagne,
        string $filePath,
        array $mapping,
        array $customFieldsMapping = [],
        string $encoding = 'UTF-8',
        string $separator = ';'
    ): ImportResult {
        $result = new ImportResult();

        $reader = Reader::createFromPath($filePath, 'r');
        $reader->setDelimiter($separator);

        if ($encoding !== 'UTF-8') {
            CharsetConverter::addTo($reader, $encoding, 'UTF-8');
        }

        $reader->setHeaderOffset(0);
        $headers = $reader->getHeader();

        // Cache des segments crees (RG-093)
        $segmentsCache = [];
        foreach ($campagne->getSegments() as $segment) {
            $segmentsCache[mb_strtolower($segment->getNom())] = $segment;
        }

        $lineNumber = 1; // La ligne 1 est l'en-tete
        foreach ($reader->getRecords() as $record) {
            $lineNumber++;

            // RG-012 : Verifier limite
            if ($lineNumber > self::MAX_LINES + 1) {
                $result->addError($lineNumber, 'general', 'Limite de 100 000 lignes atteinte.');
                break;
            }

            try {
                $operation = $this->createOperationFromRecord(
                    $campagne,
                    $record,
                    $headers,
                    $mapping,
                    $customFieldsMapping,
                    $segmentsCache,
                    $lineNumber,
                    $result
                );

                if ($operation !== null) {
                    $this->entityManager->persist($operation);
                    $result->incrementImported();
                }
            } catch (\Exception $e) {
                $result->addError($lineNumber, 'exception', $e->getMessage());
                $this->logger->warning('Import CSV error', [
                    'line' => $lineNumber,
                    'error' => $e->getMessage(),
                    'campagne_id' => $campagne->getId(),
                ]);
            }
        }

        // Persister les nouveaux segments
        foreach ($segmentsCache as $segment) {
            if ($segment->getId() === null) {
                $this->entityManager->persist($segment);
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Import CSV completed', [
            'campagne_id' => $campagne->getId(),
            'imported' => $result->getImportedCount(),
            'errors' => $result->getErrorCount(),
        ]);

        return $result;
    }

    /**
     * Cree une Operation a partir d'un enregistrement CSV.
     */
    private function createOperationFromRecord(
        Campagne $campagne,
        array $record,
        array $headers,
        array $mapping,
        array $customFieldsMapping,
        array &$segmentsCache,
        int $lineNumber,
        ImportResult $result
    ): ?Operation {
        $values = array_values($record);

        // Extraire les valeurs mappees
        $matricule = $this->getValueFromMapping($values, $mapping, 'matricule');
        $nom = $this->getValueFromMapping($values, $mapping, 'nom');
        $segmentName = $this->getValueFromMapping($values, $mapping, 'segment');
        $notes = $this->getValueFromMapping($values, $mapping, 'notes');
        $datePlanifiee = $this->getValueFromMapping($values, $mapping, 'date_planifiee');

        // Validation des champs obligatoires
        if (empty($matricule)) {
            $result->addError($lineNumber, 'matricule', 'Matricule obligatoire manquant.');
            return null;
        }

        if (empty($nom)) {
            $result->addError($lineNumber, 'nom', 'Nom obligatoire manquant.');
            return null;
        }

        // RG-014 : Creer l'operation avec statut initial
        $operation = new Operation();
        $operation->setCampagne($campagne);
        $operation->setMatricule(trim($matricule));
        $operation->setNom(trim($nom));

        // Notes
        if (!empty($notes)) {
            $operation->setNotes(trim($notes));
        }

        // Date planifiee
        if (!empty($datePlanifiee)) {
            try {
                $date = $this->parseDate($datePlanifiee);
                if ($date) {
                    $operation->setDatePlanifiee($date);
                }
            } catch (\Exception $e) {
                $result->addError($lineNumber, 'date_planifiee', 'Format de date invalide : ' . $datePlanifiee);
            }
        }

        // RG-093 : Segment auto-cree
        if (!empty($segmentName)) {
            $segmentKey = mb_strtolower(trim($segmentName));
            if (!isset($segmentsCache[$segmentKey])) {
                $segment = new Segment();
                $segment->setNom(trim($segmentName));
                $segment->setCampagne($campagne);
                $segment->setCouleur($this->getNextSegmentColor(count($segmentsCache)));
                $segmentsCache[$segmentKey] = $segment;
            }
            $operation->setSegment($segmentsCache[$segmentKey]);
        }

        // Type d'operation de la campagne
        if ($campagne->getTypeOperation() !== null) {
            $operation->setTypeOperation($campagne->getTypeOperation());
        }

        // Champs personnalises
        if (!empty($customFieldsMapping)) {
            $customData = [];
            foreach ($customFieldsMapping as $fieldCode => $headerIndex) {
                if ($headerIndex !== null && isset($values[$headerIndex])) {
                    $customData[$fieldCode] = $values[$headerIndex];
                }
            }
            if (!empty($customData)) {
                $operation->setDonneesPersonnalisees($customData);
            }
        }

        return $operation;
    }

    /**
     * Recupere une valeur depuis le mapping.
     */
    private function getValueFromMapping(array $values, array $mapping, string $field): ?string
    {
        if (!isset($mapping[$field]) || $mapping[$field] === null) {
            return null;
        }

        $index = $mapping[$field];
        return isset($values[$index]) ? trim($values[$index]) : null;
    }

    /**
     * Normalise un en-tete pour la comparaison.
     */
    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower($header);
        $header = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        $header = preg_replace('/[^a-z0-9]/', '', $header);
        return $header;
    }

    /**
     * Parse une date depuis differents formats.
     */
    private function parseDate(string $dateString): ?\DateTimeImmutable
    {
        $formats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'd.m.Y',
            'd/m/y',
        ];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, trim($dateString));
            if ($date !== false) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Retourne la prochaine couleur pour un segment.
     */
    private function getNextSegmentColor(int $index): string
    {
        $colors = array_keys(Segment::COULEURS);
        return $colors[$index % count($colors)];
    }
}
