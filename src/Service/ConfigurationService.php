<?php

namespace App\Service;

use App\Entity\ChecklistTemplate;
use App\Entity\Segment;
use App\Entity\TypeOperation;
use App\Repository\ChecklistTemplateRepository;
use App\Repository\SegmentRepository;
use App\Repository\TypeOperationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service d'export/import de la configuration.
 *
 * Regles metier implementees :
 * - RG-100 : Export en ZIP (types_operations.csv, templates_checklists.csv, segments.csv, config_metadata.json)
 * - RG-101 : Import avec gestion des conflits (Remplacer, Ignorer, Creer nouveaux)
 * - RG-102 : Compatibilite entre versions
 */
class ConfigurationService
{
    public const MODE_REMPLACER = 'remplacer';
    public const MODE_IGNORER = 'ignorer';
    public const MODE_CREER_NOUVEAUX = 'creer_nouveaux';

    public const MODES = [
        self::MODE_REMPLACER => 'Remplacer les existants',
        self::MODE_IGNORER => 'Ignorer les conflits',
        self::MODE_CREER_NOUVEAUX => 'Créer uniquement les nouveaux',
    ];

    private const VERSION = '1.0.0';

    public function __construct(
        private readonly TypeOperationRepository $typeOperationRepository,
        private readonly ChecklistTemplateRepository $checklistTemplateRepository,
        private readonly SegmentRepository $segmentRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * Exporte la configuration dans un fichier ZIP.
     *
     * @return string Chemin vers le fichier ZIP
     */
    public function exporter(): string
    {
        $tempDir = sys_get_temp_dir() . '/opstracker_config_' . uniqid();
        mkdir($tempDir, 0755, true);

        // Exporter les types d'opération
        $this->exporterTypesOperations($tempDir . '/types_operations.csv');

        // Exporter les templates de checklist
        $this->exporterTemplatesChecklists($tempDir . '/templates_checklists.csv');

        // Exporter les segments (sans campagne liée car config globale)
        $this->exporterSegmentsGlobaux($tempDir . '/segments.csv');

        // Générer les métadonnées
        $this->genererMetadata($tempDir . '/config_metadata.json');

        // Créer le ZIP
        $zipPath = sys_get_temp_dir() . '/opstracker_config_' . date('Y-m-d_His') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        foreach (glob($tempDir . '/*') as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();

        // Nettoyer le dossier temporaire
        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);

        return $zipPath;
    }

    /**
     * Importe la configuration depuis un fichier ZIP.
     *
     * @return array{success: bool, imported: array, errors: array, conflicts: array}
     */
    public function importer(UploadedFile $file, string $mode = self::MODE_CREER_NOUVEAUX): array
    {
        $result = [
            'success' => true,
            'imported' => ['types_operations' => 0, 'templates_checklists' => 0, 'segments' => 0],
            'errors' => [],
            'conflicts' => [],
        ];

        // Extraire le ZIP
        $tempDir = sys_get_temp_dir() . '/opstracker_import_' . uniqid();
        mkdir($tempDir, 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($file->getPathname()) !== true) {
            $result['success'] = false;
            $result['errors'][] = 'Impossible d\'ouvrir le fichier ZIP.';
            return $result;
        }

        $zip->extractTo($tempDir);
        $zip->close();

        // Vérifier les métadonnées
        $metadataPath = $tempDir . '/config_metadata.json';
        if (!file_exists($metadataPath)) {
            $result['errors'][] = 'Fichier config_metadata.json manquant.';
            $result['success'] = false;
            $this->nettoyerDossier($tempDir);
            return $result;
        }

        $metadata = json_decode(file_get_contents($metadataPath), true);
        if (!$this->verifierCompatibilite($metadata)) {
            $result['errors'][] = sprintf(
                'Configuration exportée depuis v%s, version actuelle v%s. Import incompatible.',
                $metadata['version'] ?? 'inconnue',
                self::VERSION
            );
            $result['success'] = false;
            $this->nettoyerDossier($tempDir);
            return $result;
        }

        $this->em->beginTransaction();

        try {
            // Importer les types d'opération
            $typesPath = $tempDir . '/types_operations.csv';
            if (file_exists($typesPath)) {
                $typesResult = $this->importerTypesOperations($typesPath, $mode);
                $result['imported']['types_operations'] = $typesResult['imported'];
                $result['conflicts'] = array_merge($result['conflicts'], $typesResult['conflicts']);
                $result['errors'] = array_merge($result['errors'], $typesResult['errors']);
            }

            // Importer les templates de checklist
            $templatesPath = $tempDir . '/templates_checklists.csv';
            if (file_exists($templatesPath)) {
                $templatesResult = $this->importerTemplatesChecklists($templatesPath, $mode);
                $result['imported']['templates_checklists'] = $templatesResult['imported'];
                $result['conflicts'] = array_merge($result['conflicts'], $templatesResult['conflicts']);
                $result['errors'] = array_merge($result['errors'], $templatesResult['errors']);
            }

            // Importer les segments
            $segmentsPath = $tempDir . '/segments.csv';
            if (file_exists($segmentsPath)) {
                $segmentsResult = $this->importerSegments($segmentsPath, $mode);
                $result['imported']['segments'] = $segmentsResult['imported'];
                $result['conflicts'] = array_merge($result['conflicts'], $segmentsResult['conflicts']);
                $result['errors'] = array_merge($result['errors'], $segmentsResult['errors']);
            }

            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            $result['success'] = false;
            $result['errors'][] = 'Erreur lors de l\'import : ' . $e->getMessage();
        }

        $this->nettoyerDossier($tempDir);

        return $result;
    }

    /**
     * Analyse un fichier ZIP sans l'importer.
     */
    public function analyser(UploadedFile $file): array
    {
        $result = [
            'valid' => true,
            'metadata' => null,
            'content' => [],
            'errors' => [],
        ];

        $tempDir = sys_get_temp_dir() . '/opstracker_analyse_' . uniqid();
        mkdir($tempDir, 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($file->getPathname()) !== true) {
            $result['valid'] = false;
            $result['errors'][] = 'Impossible d\'ouvrir le fichier ZIP.';
            return $result;
        }

        $zip->extractTo($tempDir);
        $zip->close();

        // Lire les métadonnées
        $metadataPath = $tempDir . '/config_metadata.json';
        if (file_exists($metadataPath)) {
            $result['metadata'] = json_decode(file_get_contents($metadataPath), true);
        }

        // Compter les éléments
        $typesPath = $tempDir . '/types_operations.csv';
        if (file_exists($typesPath)) {
            $result['content']['types_operations'] = $this->compterLignesCsv($typesPath);
        }

        $templatesPath = $tempDir . '/templates_checklists.csv';
        if (file_exists($templatesPath)) {
            $result['content']['templates_checklists'] = $this->compterLignesCsv($templatesPath);
        }

        $segmentsPath = $tempDir . '/segments.csv';
        if (file_exists($segmentsPath)) {
            $result['content']['segments'] = $this->compterLignesCsv($segmentsPath);
        }

        $this->nettoyerDossier($tempDir);

        return $result;
    }

    private function exporterTypesOperations(string $path): void
    {
        $types = $this->typeOperationRepository->findAll();

        $handle = fopen($path, 'w');
        fputcsv($handle, ['nom', 'description', 'icone', 'couleur', 'actif', 'champs_personnalises']);

        foreach ($types as $type) {
            fputcsv($handle, [
                $type->getNom(),
                $type->getDescription() ?? '',
                $type->getIcone(),
                $type->getCouleur(),
                $type->isActif() ? '1' : '0',
                json_encode($type->getChampsPersonnalises() ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        }

        fclose($handle);
    }

    private function exporterTemplatesChecklists(string $path): void
    {
        $templates = $this->checklistTemplateRepository->findAll();

        $handle = fopen($path, 'w');
        fputcsv($handle, ['nom', 'description', 'version', 'actif', 'structure']);

        foreach ($templates as $template) {
            fputcsv($handle, [
                $template->getNom(),
                $template->getDescription() ?? '',
                $template->getVersion(),
                $template->isActif() ? '1' : '0',
                json_encode($template->getStructure() ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        }

        fclose($handle);
    }

    private function exporterSegmentsGlobaux(string $path): void
    {
        // Exporter les noms uniques de segments utilisés dans les campagnes
        $segments = $this->segmentRepository->findAll();
        $nomsUniques = [];

        foreach ($segments as $segment) {
            $nom = $segment->getNom();
            if (!in_array($nom, $nomsUniques, true)) {
                $nomsUniques[] = $nom;
            }
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, ['nom', 'couleur']);

        foreach ($nomsUniques as $nom) {
            $segment = $this->segmentRepository->findOneBy(['nom' => $nom]);
            fputcsv($handle, [
                $nom,
                $segment ? $segment->getCouleur() : 'primary',
            ]);
        }

        fclose($handle);
    }

    private function genererMetadata(string $path): void
    {
        $metadata = [
            'version' => self::VERSION,
            'exported_at' => (new \DateTimeImmutable())->format('c'),
            'application' => 'OpsTracker',
            'counts' => [
                'types_operations' => $this->typeOperationRepository->count([]),
                'templates_checklists' => $this->checklistTemplateRepository->count([]),
            ],
        ];

        file_put_contents($path, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function importerTypesOperations(string $path, string $mode): array
    {
        $result = ['imported' => 0, 'conflicts' => [], 'errors' => []];

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            $existing = $this->typeOperationRepository->findOneBy(['nom' => $data['nom']]);

            if ($existing) {
                if ($mode === self::MODE_IGNORER) {
                    $result['conflicts'][] = sprintf('Type "%s" ignoré (existe déjà).', $data['nom']);
                    continue;
                } elseif ($mode === self::MODE_CREER_NOUVEAUX) {
                    $result['conflicts'][] = sprintf('Type "%s" non importé (existe déjà).', $data['nom']);
                    continue;
                }
                // MODE_REMPLACER : mettre à jour
                $type = $existing;
            } else {
                $type = new TypeOperation();
            }

            $type->setNom($data['nom']);
            $type->setDescription($data['description'] ?: null);
            $type->setIcone($data['icone'] ?: 'settings');
            $type->setCouleur($data['couleur'] ?: 'primary');
            $type->setActif($data['actif'] === '1');
            $type->setChampsPersonnalises(json_decode($data['champs_personnalises'], true) ?: null);

            $this->em->persist($type);
            $result['imported']++;
        }

        fclose($handle);
        $this->em->flush();

        return $result;
    }

    private function importerTemplatesChecklists(string $path, string $mode): array
    {
        $result = ['imported' => 0, 'conflicts' => [], 'errors' => []];

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            $existing = $this->checklistTemplateRepository->findOneBy(['nom' => $data['nom']]);

            if ($existing) {
                if ($mode === self::MODE_IGNORER) {
                    $result['conflicts'][] = sprintf('Template "%s" ignoré (existe déjà).', $data['nom']);
                    continue;
                } elseif ($mode === self::MODE_CREER_NOUVEAUX) {
                    $result['conflicts'][] = sprintf('Template "%s" non importé (existe déjà).', $data['nom']);
                    continue;
                }
                // MODE_REMPLACER : créer nouvelle version
                $template = $existing;
                $template->setVersion($existing->getVersion() + 1);
            } else {
                $template = new ChecklistTemplate();
            }

            $template->setNom($data['nom']);
            $template->setDescription($data['description'] ?: null);
            if (!$existing) {
                $template->setVersion((int) ($data['version'] ?? 1));
            }
            $template->setActif($data['actif'] === '1');
            $template->setStructure(json_decode($data['structure'], true) ?: []);

            $this->em->persist($template);
            $result['imported']++;
        }

        fclose($handle);
        $this->em->flush();

        return $result;
    }

    private function importerSegments(string $path, string $mode): array
    {
        // Les segments sont liés aux campagnes, on ne peut pas les importer globalement
        // On retourne juste une information
        return [
            'imported' => 0,
            'conflicts' => [],
            'errors' => ['Les segments sont liés aux campagnes et ne peuvent pas être importés globalement.'],
        ];
    }

    private function verifierCompatibilite(array $metadata): bool
    {
        // Pour l'instant, accepter toutes les versions 1.x
        $version = $metadata['version'] ?? '0.0.0';
        return version_compare($version, '1.0.0', '>=') && version_compare($version, '2.0.0', '<');
    }

    private function compterLignesCsv(string $path): int
    {
        $count = 0;
        $handle = fopen($path, 'r');
        fgetcsv($handle); // Skip header

        while (fgetcsv($handle) !== false) {
            $count++;
        }

        fclose($handle);
        return $count;
    }

    private function nettoyerDossier(string $dir): void
    {
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
