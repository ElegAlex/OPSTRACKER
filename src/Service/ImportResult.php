<?php

namespace App\Service;

/**
 * Resultat d'un import CSV.
 *
 * Contient le nombre d'operations importees et la liste des erreurs.
 * RG-092 : Fichier log genere avec numero de ligne, champ et message d'erreur.
 */
class ImportResult
{
    private int $importedCount = 0;

    /** @var array<int, array{line: int, field: string, message: string}> */
    private array $errors = [];

    public function incrementImported(): void
    {
        $this->importedCount++;
    }

    public function addError(int $line, string $field, string $message): void
    {
        $this->errors[] = [
            'line' => $line,
            'field' => $field,
            'message' => $message,
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * @return array<int, array{line: int, field: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function isSuccess(): bool
    {
        return $this->importedCount > 0;
    }

    /**
     * Genere un rapport texte des erreurs.
     */
    public function getErrorReport(): string
    {
        if (empty($this->errors)) {
            return '';
        }

        $lines = ["Rapport d'erreurs d'import CSV", str_repeat('=', 40), ''];
        $lines[] = sprintf('Total erreurs : %d', count($this->errors));
        $lines[] = '';

        foreach ($this->errors as $error) {
            $lines[] = sprintf(
                'Ligne %d | Champ : %s | %s',
                $error['line'],
                $error['field'],
                $error['message']
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Retourne un resume de l'import.
     */
    public function getSummary(): string
    {
        if ($this->importedCount === 0 && empty($this->errors)) {
            return 'Aucune donnée à importer.';
        }

        $parts = [];

        if ($this->importedCount > 0) {
            $parts[] = sprintf('%d opération(s) importée(s)', $this->importedCount);
        }

        if (!empty($this->errors)) {
            $parts[] = sprintf('%d erreur(s)', count($this->errors));
        }

        return implode(', ', $parts);
    }
}
