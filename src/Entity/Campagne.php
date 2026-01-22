<?php

namespace App\Entity;

use App\Repository\CampagneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite Campagne pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-010 : 5 statuts (Preparation, A venir, En cours, Terminee, Archivee)
 * - RG-011 : Nom + Dates (debut/fin) obligatoires a la creation
 */
#[ORM\Entity(repositoryClass: CampagneRepository::class)]
#[ORM\Table(name: 'campagne')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['nom'], message: 'Une campagne existe deja avec ce nom.')]
class Campagne
{
    // RG-010 : 5 statuts campagne
    public const STATUT_PREPARATION = 'preparation';
    public const STATUT_A_VENIR = 'a_venir';
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_TERMINEE = 'terminee';
    public const STATUT_ARCHIVEE = 'archivee';

    public const STATUTS = [
        self::STATUT_PREPARATION => 'Preparation',
        self::STATUT_A_VENIR => 'A venir',
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_TERMINEE => 'Terminee',
        self::STATUT_ARCHIVEE => 'Archivee',
    ];

    public const STATUTS_COULEURS = [
        self::STATUT_PREPARATION => 'warning',
        self::STATUT_A_VENIR => 'primary',
        self::STATUT_EN_COURS => 'success',
        self::STATUT_TERMINEE => 'complete',
        self::STATUT_ARCHIVEE => 'muted',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Le nom de la campagne est obligatoire.')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le nom doit faire au moins {{ limit }} caracteres.')]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // RG-011 : Date debut obligatoire
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de debut est obligatoire.')]
    private ?\DateTimeImmutable $dateDebut = null;

    // RG-011 : Date fin obligatoire
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'dateDebut', message: 'La date de fin doit etre posterieure a la date de debut.')]
    private ?\DateTimeImmutable $dateFin = null;

    // RG-010 : Statut avec workflow (defaut = preparation)
    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_PREPARATION;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $proprietaire = null;

    #[ORM\ManyToOne(targetEntity: TypeOperation::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?TypeOperation $typeOperation = null;

    #[ORM\ManyToOne(targetEntity: ChecklistTemplate::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ChecklistTemplate $checklistTemplate = null;

    /** @var Collection<int, Segment> */
    #[ORM\OneToMany(targetEntity: Segment::class, mappedBy: 'campagne', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $segments;

    /** @var Collection<int, Operation> */
    #[ORM\OneToMany(targetEntity: Operation::class, mappedBy: 'campagne', cascade: ['persist'], orphanRemoval: true)]
    private Collection $operations;

    /** @var Collection<int, Document> */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'campagne', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $documents;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->segments = new ArrayCollection();
        $this->operations = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
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

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

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

    public function getProprietaire(): ?Utilisateur
    {
        return $this->proprietaire;
    }

    public function setProprietaire(?Utilisateur $proprietaire): static
    {
        $this->proprietaire = $proprietaire;

        return $this;
    }

    public function getTypeOperation(): ?TypeOperation
    {
        return $this->typeOperation;
    }

    public function setTypeOperation(?TypeOperation $typeOperation): static
    {
        $this->typeOperation = $typeOperation;

        return $this;
    }

    public function getChecklistTemplate(): ?ChecklistTemplate
    {
        return $this->checklistTemplate;
    }

    public function setChecklistTemplate(?ChecklistTemplate $checklistTemplate): static
    {
        $this->checklistTemplate = $checklistTemplate;

        return $this;
    }

    /**
     * @return Collection<int, Segment>
     */
    public function getSegments(): Collection
    {
        return $this->segments;
    }

    public function addSegment(Segment $segment): static
    {
        if (!$this->segments->contains($segment)) {
            $this->segments->add($segment);
            $segment->setCampagne($this);
        }

        return $this;
    }

    public function removeSegment(Segment $segment): static
    {
        if ($this->segments->removeElement($segment)) {
            if ($segment->getCampagne() === $this) {
                $segment->setCampagne(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Operation>
     */
    public function getOperations(): Collection
    {
        return $this->operations;
    }

    public function addOperation(Operation $operation): static
    {
        if (!$this->operations->contains($operation)) {
            $this->operations->add($operation);
            $operation->setCampagne($this);
        }

        return $this;
    }

    public function removeOperation(Operation $operation): static
    {
        if ($this->operations->removeElement($operation)) {
            if ($operation->getCampagne() === $this) {
                $operation->setCampagne(null);
            }
        }

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
     * Verifie si la campagne est en lecture seule (archivee)
     * RG-016 : Campagne archivee = lecture seule
     */
    public function isReadOnly(): bool
    {
        return $this->statut === self::STATUT_ARCHIVEE;
    }

    /**
     * Verifie si la campagne est active (en cours)
     */
    public function isActive(): bool
    {
        return $this->statut === self::STATUT_EN_COURS;
    }

    /**
     * Compte le nombre d'operations
     */
    public function getNombreOperations(): int
    {
        return $this->operations->count();
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setCampagne($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getCampagne() === $this) {
                $document->setCampagne(null);
            }
        }

        return $this;
    }

    /**
     * Compte le nombre de documents
     */
    public function getNombreDocuments(): int
    {
        return $this->documents->count();
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
