<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\Document;
use App\Entity\Utilisateur;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service de gestion des documents.
 *
 * Regles metier implementees :
 * - RG-050 : Formats autorises (PDF, DOCX, PS1, BAT, ZIP, EXE), taille max 50 Mo
 * - RG-051 : Tout document doit etre associe a une campagne
 */
class DocumentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentRepository $documentRepository,
        private SluggerInterface $slugger,
        private string $uploadDirectory,
    ) {
    }

    /**
     * Upload un document pour une campagne (T-1006, RG-050, RG-051)
     *
     * @throws \InvalidArgumentException si le fichier n'est pas valide
     */
    public function upload(
        UploadedFile $file,
        Campagne $campagne,
        Utilisateur $uploadePar,
        string $type = Document::TYPE_AUTRE,
        ?string $description = null
    ): Document {
        // RG-050 : Verifier l'extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!Document::isExtensionAutorisee($extension)) {
            throw new \InvalidArgumentException(sprintf(
                'Extension non autorisee : %s. Extensions acceptees : %s',
                $extension,
                implode(', ', Document::EXTENSIONS_AUTORISEES)
            ));
        }

        // RG-050 : Verifier la taille (50 Mo max)
        if (!Document::isTailleAutorisee($file->getSize())) {
            throw new \InvalidArgumentException(sprintf(
                'Fichier trop volumineux (%.1f Mo). Taille max : 50 Mo',
                $file->getSize() / 1048576
            ));
        }

        // Generer un nom de fichier unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid(), $extension);

        // Creer le repertoire de la campagne si necessaire
        $campagneDir = $this->getUploadPath($campagne);
        if (!is_dir($campagneDir)) {
            mkdir($campagneDir, 0755, true);
        }

        // Deplacer le fichier
        $file->move($campagneDir, $newFilename);

        // Creer l'entite Document
        $document = new Document();
        $document->setNomFichier($newFilename);
        $document->setNomOriginal($file->getClientOriginalName());
        $document->setMimeType($file->getClientMimeType() ?? 'application/octet-stream');
        $document->setTaille($file->getSize());
        $document->setExtension($extension);
        $document->setType($type);
        $document->setDescription($description);
        $document->setCampagne($campagne); // RG-051
        $document->setUploadePar($uploadePar);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    /**
     * Supprime un document (T-1008)
     */
    public function delete(Document $document): void
    {
        // Supprimer le fichier physique
        $filePath = $this->getFilePath($document);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Supprimer l'entite
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }

    /**
     * Recupere les documents d'une campagne (T-1005)
     *
     * @return Document[]
     */
    public function getDocumentsByCampagne(Campagne $campagne): array
    {
        return $this->documentRepository->findByCampagne($campagne->getId());
    }

    /**
     * Recupere le chemin complet d'un fichier
     */
    public function getFilePath(Document $document): string
    {
        return $this->getUploadPath($document->getCampagne()) . '/' . $document->getNomFichier();
    }

    /**
     * Retourne le chemin d'upload pour une campagne
     */
    private function getUploadPath(Campagne $campagne): string
    {
        return $this->uploadDirectory . '/campagne-' . $campagne->getId();
    }

    /**
     * Verifie si un fichier existe
     */
    public function fileExists(Document $document): bool
    {
        return file_exists($this->getFilePath($document));
    }

    /**
     * Recupere les statistiques des documents pour une campagne
     *
     * @return array{total: int, par_type: array<string, int>, taille_totale: int}
     */
    public function getStatistiques(Campagne $campagne): array
    {
        return [
            'total' => $this->documentRepository->countByCampagne($campagne->getId()),
            'par_type' => $this->documentRepository->countByTypeForCampagne($campagne->getId()),
            'taille_totale' => $this->documentRepository->getTailleTotaleByCampagne($campagne->getId()),
        ];
    }

    /**
     * Formate une taille en octets en chaine lisible
     */
    public static function formatTaille(int $octets): string
    {
        if ($octets < 1024) {
            return $octets . ' o';
        } elseif ($octets < 1048576) {
            return round($octets / 1024, 1) . ' Ko';
        } else {
            return round($octets / 1048576, 1) . ' Mo';
        }
    }
}
