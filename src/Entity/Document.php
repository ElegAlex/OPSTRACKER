<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite Document pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-050 : Formats autorises (PDF, DOCX, PS1, BAT, ZIP, EXE), taille max 50 Mo
 * - RG-051 : Tout document doit etre associe a une campagne
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
#[ORM\Index(name: 'idx_document_campagne', columns: ['campagne_id'])]
#[ORM\HasLifecycleCallbacks]
class Document
{
    // RG-050 : Formats autorises
    public const FORMATS_AUTORISES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword',
        'ps1' => 'text/plain',
        'bat' => 'text/plain',
        'zip' => 'application/zip',
        'exe' => 'application/x-msdownload',
    ];

    public const EXTENSIONS_AUTORISEES = ['pdf', 'docx', 'doc', 'ps1', 'bat', 'zip', 'exe'];

    // RG-050 : Taille max 50 Mo (en octets)
    public const TAILLE_MAX_OCTETS = 52428800; // 50 * 1024 * 1024

    // Types de documents pour categorisation
    public const TYPE_DOCUMENTATION = 'documentation';
    public const TYPE_SCRIPT = 'script';
    public const TYPE_PROCEDURE = 'procedure';
    public const TYPE_AUTRE = 'autre';

    public const TYPES = [
        self::TYPE_DOCUMENTATION => 'Documentation',
        self::TYPE_SCRIPT => 'Script',
        self::TYPE_PROCEDURE => 'Procedure',
        self::TYPE_AUTRE => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du fichier est obligatoire.')]
    private ?string $nomFichier = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom original est obligatoire.')]
    private ?string $nomOriginal = null;

    #[ORM\Column(length: 100)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'integer')]
    private int $taille = 0;

    #[ORM\Column(length: 10)]
    private ?string $extension = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_AUTRE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * RG-051 : Tout document doit etre associe a une campagne
     */
    #[ORM\ManyToOne(targetEntity: Campagne::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Campagne $campagne = null;

    /**
     * Utilisateur qui a uploade le document
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $uploadePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomFichier(): ?string
    {
        return $this->nomFichier;
    }

    public function setNomFichier(string $nomFichier): static
    {
        $this->nomFichier = $nomFichier;
        return $this;
    }

    public function getNomOriginal(): ?string
    {
        return $this->nomOriginal;
    }

    public function setNomOriginal(string $nomOriginal): static
    {
        $this->nomOriginal = $nomOriginal;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getTaille(): int
    {
        return $this->taille;
    }

    public function setTaille(int $taille): static
    {
        $this->taille = $taille;
        return $this;
    }

    /**
     * Retourne la taille formatee (Ko, Mo)
     */
    public function getTailleFormatee(): string
    {
        if ($this->taille < 1024) {
            return $this->taille . ' o';
        } elseif ($this->taille < 1048576) {
            return round($this->taille / 1024, 1) . ' Ko';
        } else {
            return round($this->taille / 1048576, 1) . ' Mo';
        }
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): static
    {
        $this->extension = strtolower($extension);
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!array_key_exists($type, self::TYPES)) {
            throw new \InvalidArgumentException(sprintf('Type invalide : %s', $type));
        }
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCampagne(): ?Campagne
    {
        return $this->campagne;
    }

    public function setCampagne(?Campagne $campagne): static
    {
        $this->campagne = $campagne;
        return $this;
    }

    public function getUploadePar(): ?Utilisateur
    {
        return $this->uploadePar;
    }

    public function setUploadePar(?Utilisateur $uploadePar): static
    {
        $this->uploadePar = $uploadePar;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Verifie si l'extension est autorisee (RG-050)
     */
    public static function isExtensionAutorisee(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONS_AUTORISEES, true);
    }

    /**
     * Verifie si la taille est dans la limite (RG-050)
     */
    public static function isTailleAutorisee(int $taille): bool
    {
        return $taille <= self::TAILLE_MAX_OCTETS;
    }

    /**
     * Verifie si le fichier est un script (extension executable)
     */
    public function isScript(): bool
    {
        return in_array($this->extension, ['ps1', 'bat', 'exe'], true);
    }

    /**
     * Retourne l'icone correspondant au type de fichier
     */
    public function getIcone(): string
    {
        return match($this->extension) {
            'pdf' => 'file-text',
            'docx', 'doc' => 'file-text',
            'ps1', 'bat' => 'terminal',
            'exe' => 'cpu',
            'zip' => 'archive',
            default => 'file',
        };
    }

    public function __toString(): string
    {
        return $this->nomOriginal ?? '';
    }
}
