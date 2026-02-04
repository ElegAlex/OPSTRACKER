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

    // Champs systeme mappables vers Operation (les autres vont dans donneesPersonnalisees)
    // RG-015 : TOUTES les donnees metier passent par donneesPersonnalisees (JSONB)
    public const MAPPABLE_FIELDS = [
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
     *
     * Gere les encodages courants des fichiers Excel francais :
     * - UTF-8 (avec ou sans BOM)
     * - Windows-1252 (Excel par defaut)
     * - ISO-8859-1 (Latin-1)
     *
     * IMPORTANT: Priorite a la detection UTF-8 pour eviter le double encodage !
     */
    public function detectEncoding(string $filePath): string
    {
        $content = file_get_contents($filePath, false, null, 0, 16384);
        if ($content === false) {
            return 'UTF-8';
        }

        // PRIORITE 1 : Detecter le BOM UTF-8
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }

        // PRIORITE 2 : Verifier si c'est du UTF-8 valide
        // C'est LE test le plus important pour eviter le double encodage !
        if ($this->isValidUtf8($content)) {
            return 'UTF-8';
        }

        // PRIORITE 3 : Ce n'est PAS du UTF-8 valide, donc c'est un encodage 8-bit
        // Caracteres typiques Windows-1252 dans plage 0x80-0x9F (€, œ, etc.)
        if (preg_match('/[\x80-\x9F]/', $content)) {
            return 'Windows-1252';
        }

        // Par defaut : ISO-8859-1 (fichiers Excel francais)
        return 'ISO-8859-1';
    }

    /**
     * Verifie si le contenu est du UTF-8 valide.
     *
     * Utilise mb_check_encoding qui est fiable pour detecter les sequences
     * multi-octets UTF-8 invalides (ex: bytes isoles 0x80-0xFF).
     */
    private function isValidUtf8(string $content): bool
    {
        // mb_check_encoding retourne false si le contenu contient des
        // sequences UTF-8 invalides (bytes isoles > 127, sequences mal formees)
        return mb_check_encoding($content, 'UTF-8');
    }

    /**
     * Convertit le contenu en UTF-8.
     */
    private function convertToUtf8(string $content, string $sourceEncoding): string
    {
        if ($sourceEncoding === 'UTF-8') {
            return $this->removeBom($content);
        }

        // Conversion avec mb_convert_encoding
        $converted = mb_convert_encoding($content, 'UTF-8', $sourceEncoding);
        if ($converted !== false && $converted !== '') {
            return $converted;
        }

        // Fallback avec iconv (gere mieux certains caracteres)
        $converted = @iconv($sourceEncoding, 'UTF-8//TRANSLIT//IGNORE', $content);
        if ($converted !== false && $converted !== '') {
            return $converted;
        }

        // Dernier recours : retourner le contenu original
        $this->logger->warning('Encoding conversion failed', [
            'source_encoding' => $sourceEncoding,
        ]);

        return $content;
    }

    /**
     * Supprime le BOM (Byte Order Mark) du contenu.
     */
    private function removeBom(string $content): string
    {
        // BOM UTF-8 (EF BB BF)
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }
        // BOM UTF-16 LE (FF FE)
        if (str_starts_with($content, "\xFF\xFE")) {
            return substr($content, 2);
        }
        // BOM UTF-16 BE (FE FF)
        if (str_starts_with($content, "\xFE\xFF")) {
            return substr($content, 2);
        }

        return $content;
    }

    /**
     * Detecte le separateur d'un fichier CSV.
     * RG-012 : Separateur auto-detecte
     */
    public function detectSeparator(string $filePath, string $encoding = 'UTF-8'): string
    {
        $content = file_get_contents($filePath, false, null, 0, 8192);
        if ($content === false) {
            return ';';
        }

        // Convertir si necessaire
        if ($encoding !== 'UTF-8') {
            $converted = mb_convert_encoding($content, 'UTF-8', $encoding);
            if (is_string($converted)) {
                $content = $converted;
            }
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

        // Convertir le fichier en UTF-8 si necessaire
        $workingPath = $filePath;
        $tempFile = null;

        if ($encoding !== 'UTF-8') {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return [
                    'headers' => [],
                    'preview' => [],
                    'total_lines' => 0,
                    'encoding' => $encoding,
                    'separator' => $separator,
                ];
            }
            $utf8Content = $this->convertToUtf8($content, $encoding);

            // Creer un fichier temporaire UTF-8
            $tempFile = tempnam(sys_get_temp_dir(), 'csv_utf8_');
            if ($tempFile === false) {
                return [
                    'headers' => [],
                    'preview' => [],
                    'total_lines' => 0,
                    'encoding' => $encoding,
                    'separator' => $separator,
                ];
            }
            file_put_contents($tempFile, $utf8Content);
            $workingPath = $tempFile;
        } else {
            // Meme en UTF-8, retirer le BOM si present
            $content = file_get_contents($filePath);
            if ($content === false) {
                return [
                    'headers' => [],
                    'preview' => [],
                    'total_lines' => 0,
                    'encoding' => $encoding,
                    'separator' => $separator,
                ];
            }
            $cleanContent = $this->removeBom($content);
            if ($cleanContent !== $content) {
                $tempFile = tempnam(sys_get_temp_dir(), 'csv_nobom_');
                if ($tempFile !== false) {
                    file_put_contents($tempFile, $cleanContent);
                    $workingPath = $tempFile;
                }
            }
        }

        try {
            $reader = Reader::createFromPath($workingPath, 'r');
            $reader->setDelimiter($separator);
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
        } finally {
            // Nettoyer le fichier temporaire
            if (is_string($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
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

        // Patterns pour chaque champ systeme
        // RG-015 : matricule et nom ne sont plus des champs speciaux, ils passent par donneesPersonnalisees
        $patterns = [
            'segment' => ['segment', 'batiment', 'building', 'etage', 'floor', 'site', 'localisation', 'service', 'departement'],
            'notes' => ['notes', 'note', 'commentaire', 'comment', 'remarque', 'observation', 'description'],
            'date_planifiee' => ['date', 'date_planifiee', 'date_prevue', 'planification', 'echeance'],
        ];

        foreach (self::MAPPABLE_FIELDS as $field => $config) {
            $mapping[$field] = null;

            $fieldPatterns = $patterns[$field];
            foreach ($fieldPatterns as $pattern) {
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
     * @param array<string, int> $customFieldsMapping Mapping nom champ => header_index
     * @param string|null $colonneDatePlanifiee Nom de la colonne CSV contenant la date
     * @param string|null $colonneHoraire Nom de la colonne CSV contenant l'horaire (optionnel)
     * @return ImportResult
     */
    public function import(
        Campagne $campagne,
        string $filePath,
        array $mapping,
        array $customFieldsMapping = [],
        string $encoding = 'UTF-8',
        string $separator = ';',
        ?string $colonneDatePlanifiee = null,
        ?string $colonneHoraire = null
    ): ImportResult {
        $result = new ImportResult();

        // Convertir le fichier en UTF-8 si necessaire (plus fiable que CharsetConverter)
        $workingPath = $filePath;
        $tempFile = null;

        if ($encoding !== 'UTF-8') {
            $content = file_get_contents($filePath);
            if ($content === false) {
                $result->addError(0, 'file', 'Impossible de lire le fichier.');
                return $result;
            }
            $utf8Content = $this->convertToUtf8($content, $encoding);

            $tempFile = tempnam(sys_get_temp_dir(), 'csv_import_');
            if ($tempFile === false) {
                $result->addError(0, 'file', 'Impossible de créer un fichier temporaire.');
                return $result;
            }
            file_put_contents($tempFile, $utf8Content);
            $workingPath = $tempFile;

            $this->logger->info('CSV converted to UTF-8', [
                'original_encoding' => $encoding,
                'campagne_id' => $campagne->getId(),
            ]);
        } else {
            // Meme en UTF-8, retirer le BOM si present
            $content = file_get_contents($filePath);
            if ($content === false) {
                $result->addError(0, 'file', 'Impossible de lire le fichier.');
                return $result;
            }
            $cleanContent = $this->removeBom($content);
            if ($cleanContent !== $content) {
                $tempFile = tempnam(sys_get_temp_dir(), 'csv_nobom_');
                if ($tempFile !== false) {
                    file_put_contents($tempFile, $cleanContent);
                    $workingPath = $tempFile;
                }
            }
        }

        try {
            $reader = Reader::createFromPath($workingPath, 'r');
            $reader->setDelimiter($separator);
            $reader->setHeaderOffset(0);
            $headers = $reader->getHeader();

            // Cache des segments crees (RG-093)
            $segmentsCache = [];
            foreach ($campagne->getSegments() as $segment) {
                $segmentsCache[mb_strtolower((string) $segment->getNom())] = $segment;
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
                        $result,
                        $colonneDatePlanifiee,
                        $colonneHoraire
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
        } finally {
            // Nettoyer le fichier temporaire
            if (is_string($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Cree une Operation a partir d'un enregistrement CSV.
     *
     * RG-015 : TOUTES les donnees metier passent par donneesPersonnalisees (JSONB)
     * Plus de champs matricule/nom dedies, tout vient des CampagneChamp.
     *
     * @param array<string, string> $record
     * @param array<int, string> $headers
     * @param array<string, int|null> $mapping
     * @param array<string, int> $customFieldsMapping
     * @param array<string, Segment> $segmentsCache
     */
    private function createOperationFromRecord(
        Campagne $campagne,
        array $record,
        array $headers,
        array $mapping,
        array $customFieldsMapping,
        array &$segmentsCache,
        int $lineNumber,
        ImportResult $result,
        ?string $colonneDatePlanifiee = null,
        ?string $colonneHoraire = null
    ): ?Operation {
        $values = array_values($record);

        // Extraire les valeurs des champs systeme (segment, notes, date_planifiee)
        $segmentName = $this->getValueFromMapping($values, $mapping, 'segment');
        $notes = $this->getValueFromMapping($values, $mapping, 'notes');
        $datePlanifiee = $this->getValueFromMapping($values, $mapping, 'date_planifiee');

        // RG-015 : Toutes les donnees metier vont dans donneesPersonnalisees
        // Les champs sont definis par les CampagneChamp
        $donneesPersonnalisees = [];
        if (!empty($customFieldsMapping)) {
            foreach ($customFieldsMapping as $fieldCode => $headerIndex) {
                if (isset($values[$headerIndex])) {
                    $donneesPersonnalisees[$fieldCode] = $values[$headerIndex];
                }
            }
        }

        // Verification qu'il y a au moins une donnee (ligne non vide)
        if (empty($donneesPersonnalisees)) {
            $result->addError($lineNumber, 'data', 'Ligne vide ou aucune donnee mappee.');
            return null;
        }

        // RG-014 : Creer l'operation avec statut initial
        $operation = new Operation();
        $operation->setCampagne($campagne);
        $operation->setDonneesPersonnalisees($donneesPersonnalisees);

        // Appliquer la capacite par defaut de la campagne
        $operation->setCapacite($campagne->getCapaciteParDefaut());

        // Notes
        if (!empty($notes)) {
            $operation->setNotes(trim($notes));
        }

        // Date planifiee depuis le mapping systeme (ancien comportement)
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

        // Date planifiee depuis le mapping de colonnes (nouveau comportement)
        if ($colonneDatePlanifiee && isset($donneesPersonnalisees[$colonneDatePlanifiee])) {
            $dateStr = trim((string) $donneesPersonnalisees[$colonneDatePlanifiee]);
            $heureStr = '';

            if ($colonneHoraire && isset($donneesPersonnalisees[$colonneHoraire])) {
                $heureStr = trim((string) $donneesPersonnalisees[$colonneHoraire]);
            }

            if (!empty($dateStr)) {
                try {
                    $dateTime = $this->parseDateWithTime($dateStr, $heureStr);
                    if ($dateTime) {
                        $operation->setDatePlanifiee($dateTime);
                    }
                } catch (\Exception $e) {
                    $result->addError($lineNumber, 'date_planifiee', 'Format de date/heure invalide : ' . $dateStr . ' ' . $heureStr);
                }
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

        return $operation;
    }

    /**
     * Recupere une valeur depuis le mapping.
     *
     * @param array<int, string> $values
     * @param array<string, int|null> $mapping
     */
    private function getValueFromMapping(array $values, array $mapping, string $field): ?string
    {
        if (!array_key_exists($field, $mapping) || $mapping[$field] === null) {
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
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        if ($converted === false) {
            $converted = $header;
        }
        $result = preg_replace('/[^a-z0-9]/', '', $converted);
        return $result ?? $converted;
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
     * Parse une date avec une heure optionnelle.
     *
     * @param string $dateStr Date (ex: "10/02/2026", "2026-02-10")
     * @param string $timeStr Heure optionnelle (ex: "15:00", "15h00", "15:00:00")
     */
    private function parseDateWithTime(string $dateStr, string $timeStr = ''): ?\DateTimeImmutable
    {
        // Normaliser l'heure (15h00 -> 15:00)
        $timeStr = str_replace(['h', 'H'], ':', trim($timeStr));

        // Si l'heure est vide, utiliser minuit
        if (empty($timeStr)) {
            $timeStr = '00:00';
        }

        // S'assurer que l'heure a le format HH:MM
        if (preg_match('/^(\d{1,2}):?(\d{2})?(?::\d{2})?$/', $timeStr, $matches)) {
            $hours = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $minutes = isset($matches[2]) ? $matches[2] : '00';
            $timeStr = "$hours:$minutes";
        }

        // Formats de date a tester
        $dateFormats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'd.m.Y',
            'd/m/y',
        ];

        // Essayer chaque format de date avec l'heure
        foreach ($dateFormats as $dateFormat) {
            $fullFormat = $dateFormat . ' H:i';
            $dateTimeStr = trim($dateStr) . ' ' . $timeStr;

            $dateTime = \DateTimeImmutable::createFromFormat($fullFormat, $dateTimeStr);
            if ($dateTime !== false) {
                return $dateTime;
            }
        }

        // Fallback : essayer de parser avec la date seule
        return $this->parseDate($dateStr);
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
