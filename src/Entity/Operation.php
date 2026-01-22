<?php

namespace App\Entity;

use App\Repository\OperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite Operation pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-014 : Statut initial = "A planifier"
 * - RG-015 : Donnees personnalisees stockees en PostgreSQL JSONB
 * - RG-017 : 6 statuts avec transitions definies
 * - RG-018 : 1 operation = 1 technicien assigne maximum
 * - RG-021 : Motif de report optionnel
 */
#[ORM\Entity(repositoryClass: OperationRepository::class)]
#[ORM\Table(name: 'operation')]
#[ORM\Index(name: 'idx_operation_statut', columns: ['statut'])]
#[ORM\Index(name: 'idx_operation_matricule', columns: ['matricule'])]
#[ORM\HasLifecycleCallbacks]
class Operation
{
    // RG-017 : 6 statuts operation
    public const STATUT_A_PLANIFIER = 'a_planifier';
    public const STATUT_PLANIFIE = 'planifie';
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_REALISE = 'realise';
    public const STATUT_REPORTE = 'reporte';
    public const STATUT_A_REMEDIER = 'a_remedier';

    public const STATUTS = [
        self::STATUT_A_PLANIFIER => 'A planifier',
        self::STATUT_PLANIFIE => 'Planifie',
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_REALISE => 'Realise',
        self::STATUT_REPORTE => 'Reporte',
        self::STATUT_A_REMEDIER => 'A remedier',
    ];

    public const STATUTS_COULEURS = [
        self::STATUT_A_PLANIFIER => 'muted',
        self::STATUT_PLANIFIE => 'primary',
        self::STATUT_EN_COURS => 'primary',
        self::STATUT_REALISE => 'success',
        self::STATUT_REPORTE => 'warning',
        self::STATUT_A_REMEDIER => 'danger',
    ];

    // Statuts finaux (terminaux)
    public const STATUTS_FINAUX = [
        self::STATUT_REALISE,
        self::STATUT_REPORTE,
        self::STATUT_A_REMEDIER,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Matricule unique de l'operation (identifiant metier)
     * Ex: MAT-001, PC-2024-0001, etc.
     */
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le matricule est obligatoire.')]
    private ?string $matricule = null;

    /**
     * Nom/libelle de l'operation
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    private ?string $nom = null;

    /**
     * RG-014 : Statut initial = "A planifier"
     */
    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_A_PLANIFIER;

    /**
     * RG-015 : Donnees personnalisees en JSONB
     * Structure libre definie par le TypeOperation
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $donneesPersonnalisees = null;

    /**
     * RG-021 : Motif de report (optionnel)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motifReport = null;

    /**
     * Notes/commentaires libres
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Date planifiee pour l'intervention
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datePlanifiee = null;

    /**
     * Date de realisation effective
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateRealisation = null;

    #[ORM\ManyToOne(targetEntity: Campagne::class, inversedBy: 'operations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Campagne $campagne = null;

    #[ORM\ManyToOne(targetEntity: Segment::class, inversedBy: 'operations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Segment $segment = null;

    #[ORM\ManyToOne(targetEntity: TypeOperation::class, inversedBy: 'operations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?TypeOperation $typeOperation = null;

    /**
     * RG-018 : 1 technicien assigne maximum
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $technicienAssigne = null;

    #[ORM\OneToOne(targetEntity: ChecklistInstance::class, mappedBy: 'operation', cascade: ['persist', 'remove'])]
    private ?ChecklistInstance $checklistInstance = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
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

    /**
     * Verifie si l'operation est dans un etat final
     */
    public function isTerminee(): bool
    {
        return in_array($this->statut, self::STATUTS_FINAUX, true);
    }

    public function getDonneesPersonnalisees(): ?array
    {
        return $this->donneesPersonnalisees;
    }

    public function setDonneesPersonnalisees(?array $donneesPersonnalisees): static
    {
        $this->donneesPersonnalisees = $donneesPersonnalisees;

        return $this;
    }

    /**
     * Recupere une valeur d'un champ personnalise
     */
    public function getDonneePersonnalisee(string $code): mixed
    {
        return $this->donneesPersonnalisees[$code] ?? null;
    }

    /**
     * Definit une valeur pour un champ personnalise
     */
    public function setDonneePersonnalisee(string $code, mixed $valeur): static
    {
        if ($this->donneesPersonnalisees === null) {
            $this->donneesPersonnalisees = [];
        }
        $this->donneesPersonnalisees[$code] = $valeur;

        return $this;
    }

    public function getMotifReport(): ?string
    {
        return $this->motifReport;
    }

    public function setMotifReport(?string $motifReport): static
    {
        $this->motifReport = $motifReport;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getDatePlanifiee(): ?\DateTimeImmutable
    {
        return $this->datePlanifiee;
    }

    public function setDatePlanifiee(?\DateTimeImmutable $datePlanifiee): static
    {
        $this->datePlanifiee = $datePlanifiee;

        return $this;
    }

    public function getDateRealisation(): ?\DateTimeImmutable
    {
        return $this->dateRealisation;
    }

    public function setDateRealisation(?\DateTimeImmutable $dateRealisation): static
    {
        $this->dateRealisation = $dateRealisation;

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

    public function getSegment(): ?Segment
    {
        return $this->segment;
    }

    public function setSegment(?Segment $segment): static
    {
        $this->segment = $segment;

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

    public function getTechnicienAssigne(): ?Utilisateur
    {
        return $this->technicienAssigne;
    }

    public function setTechnicienAssigne(?Utilisateur $technicienAssigne): static
    {
        $this->technicienAssigne = $technicienAssigne;

        return $this;
    }

    public function getChecklistInstance(): ?ChecklistInstance
    {
        return $this->checklistInstance;
    }

    public function setChecklistInstance(?ChecklistInstance $checklistInstance): static
    {
        // Gestion de la relation bidirectionnelle
        if ($checklistInstance === null && $this->checklistInstance !== null) {
            $this->checklistInstance->setOperation(null);
        }

        if ($checklistInstance !== null && $checklistInstance->getOperation() !== $this) {
            $checklistInstance->setOperation($this);
        }

        $this->checklistInstance = $checklistInstance;

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

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->matricule, $this->nom);
    }
}
