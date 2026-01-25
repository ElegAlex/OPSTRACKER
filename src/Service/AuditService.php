<?php

namespace App\Service;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;

/**
 * Service de consultation de l'historique d'audit.
 *
 * Regles metier implementees :
 * - RG-070 : Audit trail complet (qui, quoi, quand)
 * - RG-071 : Rétention 2 ans minimum
 */
class AuditService
{
    public function __construct(
        private readonly Reader $reader
    ) {
    }

    /**
     * Recupere l'historique d'une entite.
     *
     * @param string $entityClass Classe de l'entite (ex: App\Entity\Campagne)
     * @param int $entityId ID de l'entite
     * @param int $page Numero de page (commence a 1)
     * @param int $pageSize Nombre d'elements par page
     *
     * @return array{entries: array, total: int, page: int, pageSize: int, totalPages: int}
     */
    public function getHistorique(string $entityClass, int $entityId, int $page = 1, int $pageSize = 20): array
    {
        // Recuperer les entrees d'audit
        $entries = $this->reader->createQuery($entityClass)
            ->filterBy(['object_id' => $entityId])
            ->orderBy(['created_at' => 'DESC'])
            ->execute();

        $total = count($entries);
        $totalPages = (int) ceil($total / $pageSize);
        $page = max(1, min($page, $totalPages ?: 1));
        $offset = ($page - 1) * $pageSize;

        $pagedEntries = array_slice($entries, $offset, $pageSize);

        return [
            'entries' => $this->formatEntries($pagedEntries),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Recupere l'historique d'une campagne avec toutes ses operations.
     */
    public function getHistoriqueCampagne(int $campagneId, int $page = 1, int $pageSize = 20): array
    {
        return $this->getHistorique('App\Entity\Campagne', $campagneId, $page, $pageSize);
    }

    /**
     * Recupere l'historique global pour une periode.
     *
     * @param \DateTimeInterface|null $dateDebut
     * @param \DateTimeInterface|null $dateFin
     * @param string|null $entityType Type d'entite a filtrer
     * @param string|null $utilisateur Email de l'utilisateur
     */
    public function getHistoriqueGlobal(
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null,
        ?string $entityType = null,
        ?string $utilisateur = null,
        int $page = 1,
        int $pageSize = 50
    ): array {
        // Liste des entites auditees
        $entityClasses = [
            'campagne' => 'App\Entity\Campagne',
            'operation' => 'App\Entity\Operation',
            'utilisateur' => 'App\Entity\Utilisateur',
            'type_operation' => 'App\Entity\TypeOperation',
            'checklist_template' => 'App\Entity\ChecklistTemplate',
            'checklist_instance' => 'App\Entity\ChecklistInstance',
            'segment' => 'App\Entity\Segment',
            'document' => 'App\Entity\Document',
        ];

        $allEntries = [];

        $classesToQuery = $entityType && isset($entityClasses[$entityType])
            ? [$entityClasses[$entityType]]
            : array_values($entityClasses);

        foreach ($classesToQuery as $entityClass) {
            try {
                $query = $this->reader->createQuery($entityClass);

                $filters = [];
                if ($utilisateur) {
                    $filters['blame_user'] = $utilisateur;
                }

                if (!empty($filters)) {
                    $query->filterBy($filters);
                }

                $entries = $query->orderBy(['created_at' => 'DESC'])->execute();

                // Filtrer par date
                foreach ($entries as $entry) {
                    $createdAt = $entry->getCreatedAt();

                    if ($dateDebut && $createdAt < $dateDebut) {
                        continue;
                    }
                    if ($dateFin && $createdAt > $dateFin) {
                        continue;
                    }

                    $allEntries[] = [
                        'entry' => $entry,
                        'entityClass' => $entityClass,
                    ];
                }
            } catch (\Exception $e) {
                // Ignorer les entites sans historique
            }
        }

        // Trier par date decroissante
        usort($allEntries, function ($a, $b) {
            return $b['entry']->getCreatedAt() <=> $a['entry']->getCreatedAt();
        });

        $total = count($allEntries);
        $totalPages = (int) ceil($total / $pageSize);
        $page = max(1, min($page, $totalPages ?: 1));
        $offset = ($page - 1) * $pageSize;

        $pagedEntries = array_slice($allEntries, $offset, $pageSize);

        return [
            'entries' => $this->formatEntriesWithClass($pagedEntries),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => $totalPages,
            'entityTypes' => array_keys($entityClasses),
        ];
    }

    /**
     * Formate les entrees d'audit pour l'affichage.
     */
    private function formatEntries(array $entries): array
    {
        $formatted = [];

        foreach ($entries as $entry) {
            $formatted[] = $this->formatEntry($entry);
        }

        return $formatted;
    }

    private function formatEntriesWithClass(array $entries): array
    {
        $formatted = [];

        foreach ($entries as $item) {
            $entry = $this->formatEntry($item['entry']);
            $entry['entityClass'] = $this->getEntityLabel($item['entityClass']);
            $formatted[] = $entry;
        }

        return $formatted;
    }

    private function formatEntry($entry): array
    {
        $diffs = $entry->getDiffs() ?? [];

        return [
            'id' => $entry->getId(),
            'type' => $this->translateType($entry->getType()),
            'typeCode' => $entry->getType(),
            'objectId' => $entry->getObjectId(),
            'createdAt' => $entry->getCreatedAt(),
            'user' => $entry->getBlameUser() ?? 'Système',
            'userId' => $entry->getBlameId(),
            'ip' => $entry->getIp(),
            'diffs' => $this->formatDiffs($diffs),
            'rawDiffs' => $diffs,
        ];
    }

    private function formatDiffs(array $diffs): array
    {
        $formatted = [];

        foreach ($diffs as $field => $values) {
            $formatted[] = [
                'field' => $this->translateField($field),
                'fieldCode' => $field,
                'old' => $this->formatValue($values['old'] ?? null),
                'new' => $this->formatValue($values['new'] ?? null),
            ];
        }

        return $formatted;
    }

    private function formatValue($value): string
    {
        if ($value === null) {
            return '(vide)';
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i:s');
        }

        $str = (string) $value;
        if (strlen($str) > 100) {
            return substr($str, 0, 100) . '...';
        }

        return $str;
    }

    private function translateType(string $type): string
    {
        return match ($type) {
            'insert' => 'Création',
            'update' => 'Modification',
            'remove' => 'Suppression',
            'associate' => 'Association',
            'dissociate' => 'Dissociation',
            default => $type,
        };
    }

    private function translateField(string $field): string
    {
        $translations = [
            'nom' => 'Nom',
            'description' => 'Description',
            'statut' => 'Statut',
            'dateDebut' => 'Date de début',
            'dateFin' => 'Date de fin',
            'actif' => 'Actif',
            'email' => 'Email',
            'prenom' => 'Prénom',
            'roles' => 'Rôles',
            'technicienAssigne' => 'Technicien assigné',
            'segment' => 'Segment',
            'matricule' => 'Matricule',
            'donnees' => 'Données',
            'notes' => 'Notes',
            'motifReport' => 'Motif de report',
            'couleur' => 'Couleur',
            'icone' => 'Icône',
            'champsPersonnalises' => 'Champs personnalisés',
            'visibilite' => 'Visibilité',
            'proprietaire' => 'Propriétaire',
        ];

        return $translations[$field] ?? $field;
    }

    private function getEntityLabel(string $entityClass): string
    {
        $labels = [
            'App\Entity\Campagne' => 'Campagne',
            'App\Entity\Operation' => 'Opération',
            'App\Entity\Utilisateur' => 'Utilisateur',
            'App\Entity\TypeOperation' => 'Type d\'opération',
            'App\Entity\ChecklistTemplate' => 'Template checklist',
            'App\Entity\ChecklistInstance' => 'Instance checklist',
            'App\Entity\Segment' => 'Segment',
            'App\Entity\Document' => 'Document',
        ];

        return $labels[$entityClass] ?? $entityClass;
    }
}
