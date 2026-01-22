<?php

namespace App\Entity;

use App\Repository\PrerequisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite Prerequis pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-090 : Prerequis globaux de campagne avec statut (A faire / En cours / Fait) - indicateur declaratif, NON bloquant
 * - RG-091 : Prerequis specifiques a un segment - indicateur declaratif, NON bloquant
 */
#[ORM\Entity(repositoryClass: PrerequisRepository::class)]
#[ORM\Table(name: 'prerequis')]
#[ORM\HasLifecycleCallbacks]
class Prerequis
{
    // RG-090 : 3 statuts pour les prerequis
    public const STATUT_A_FAIRE = 'a_faire';
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_FAIT = 'fait';

    public const STATUTS = [
        self::STATUT_A_FAIRE => 'A faire',
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_FAIT => 'Fait',
    ];

    public const STATUTS_COULEURS = [
        self::STATUT_A_FAIRE => 'muted',
        self::STATUT_EN_COURS => 'primary',
        self::STATUT_FAIT => 'success',
    ];

    public const STATUTS_ICONES = [
        self::STATUT_A_FAIRE => 'circle',
        self::STATUT_EN_COURS => 'clock',
        self::STATUT_FAIT => 'check-circle',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le libelle du prerequis est obligatoire.')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le libelle doit faire au moins {{ limit }} caracteres.')]
    private ?string $libelle = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $responsable = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateCible = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_A_FAIRE;

    /**
     * RG-090 : Campagne associee (prerequis global)
     */
    #[ORM\ManyToOne(targetEntity: Campagne::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Campagne $campagne = null;

    /**
     * RG-091 : Segment associe (null = prerequis global, non-null = prerequis segment)
     */
    #[ORM\ManyToOne(targetEntity: Segment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Segment $segment = null;

    #[ORM\Column(type: 'integer')]
    private int $ordre = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getResponsable(): ?string
    {
        return $this->responsable;
    }

    public function setResponsable(?string $responsable): static
    {
        $this->responsable = $responsable;

        return $this;
    }

    public function getDateCible(): ?\DateTimeImmutable
    {
        return $this->dateCible;
    }

    public function setDateCible(?\DateTimeImmutable $dateCible): static
    {
        $this->dateCible = $dateCible;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (!array_key_exists($statut, self::STATUTS)) {
            throw new \InvalidArgumentException(sprintf('Statut invalide : %s', $statut));
        }
        $this->statut = $statut;

        return $this;
    }

    public function getStatutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getStatutCouleur(): string
    {
        return self::STATUTS_COULEURS[$this->statut] ?? 'muted';
    }

    public function getStatutIcone(): string
    {
        return self::STATUTS_ICONES[$this->statut] ?? 'circle';
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

    public function getSegment(): ?Segment
    {
        return $this->segment;
    }

    public function setSegment(?Segment $segment): static
    {
        $this->segment = $segment;

        return $this;
    }

    /**
     * RG-090/RG-091 : Determine si c'est un prerequis global ou de segment
     */
    public function isGlobal(): bool
    {
        return $this->segment === null;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

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
     * Verifie si le prerequis est en retard (date cible depassee et non fait)
     */
    public function isEnRetard(): bool
    {
        if ($this->statut === self::STATUT_FAIT) {
            return false;
        }

        if ($this->dateCible === null) {
            return false;
        }

        return $this->dateCible < new \DateTimeImmutable('today');
    }

    public function __toString(): string
    {
        return $this->libelle ?? '';
    }
}
