<?php

namespace App\Entity;

use App\Repository\OperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite Operation pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-014 : Statut initial = "A planifier"
 * - RG-015 : TOUTES les donnees sont stockees en PostgreSQL JSONB (donneesPersonnalisees)
 * - RG-017 : 6 statuts avec transitions definies
 * - RG-018 : 1 operation = 1 technicien assigne maximum
 * - RG-021 : Motif de report optionnel
 *
 * IMPORTANT: Les champs matricule et nom ne sont plus des proprietes.
 * Ils sont stockes dans donneesPersonnalisees et definis par les CampagneChamp.
 */
#[ORM\Entity(repositoryClass: OperationRepository::class)]
#[ORM\Table(name: 'operation')]
#[ORM\Index(name: 'idx_operation_statut', columns: ['statut'])]
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
     * RG-014 : Statut initial = "A planifier"
     */
    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_A_PLANIFIER;

    /**
     * RG-015 : TOUTES les donnees personnalisees en JSONB
     * Contient toutes les valeurs definies par les CampagneChamp
     * Ex: {"Matricule": "MAT-001", "Nom": "Jean Dupont", "Bureau": "A123"}
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
     * Date et heure planifiees pour l'intervention
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
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

    /**
     * Identifiant de la personne ayant reserve cette operation (mode reservation publique)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reservePar = null;

    /**
     * Date et heure de la reservation
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reserveLe = null;

    /**
     * Informations completes sur la personne ayant reserve (JSON)
     * Structure : { "identifiant": "A018", "nomPrenom": "Louise Fournier", "service": "Prestations", "email": "..." }
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $reserveParInfos = null;

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * Retourne une representation textuelle de l'operation.
     * Utilise les premieres valeurs de donneesPersonnalisees.
     */
    public function __toString(): string
    {
        $data = $this->donneesPersonnalisees ?? [];
        $values = array_slice(array_values($data), 0, 2);

        if (count($values) >= 2) {
            return sprintf('%s - %s', $values[0], $values[1]);
        } elseif (count($values) === 1) {
            return (string) $values[0];
        }

        return sprintf('Operation #%d', $this->id ?? 0);
    }

    /**
     * Retourne le premier champ (souvent Matricule) pour affichage.
     * Methode de commodite pour les templates.
     */
    public function getDisplayIdentifier(): ?string
    {
        $data = $this->donneesPersonnalisees ?? [];
        $values = array_values($data);

        return $values[0] ?? null;
    }

    /**
     * Retourne le deuxieme champ (souvent Nom) pour affichage.
     * Methode de commodite pour les templates.
     */
    public function getDisplayName(): ?string
    {
        $data = $this->donneesPersonnalisees ?? [];
        $values = array_values($data);

        return $values[1] ?? null;
    }

    /**
     * Identifiant de la personne ayant reserve cette operation
     */
    public function getReservePar(): ?string
    {
        return $this->reservePar;
    }

    public function setReservePar(?string $reservePar): static
    {
        $this->reservePar = $reservePar;

        return $this;
    }

    /**
     * Date et heure de la reservation
     */
    public function getReserveLe(): ?\DateTimeImmutable
    {
        return $this->reserveLe;
    }

    public function setReserveLe(?\DateTimeImmutable $reserveLe): static
    {
        $this->reserveLe = $reserveLe;

        return $this;
    }

    /**
     * Verifie si l'operation est disponible a la reservation
     */
    public function isDisponible(): bool
    {
        return $this->reservePar === null;
    }

    /**
     * Reserve l'operation pour une personne
     *
     * @param string $identifiant Identifiant de la personne
     * @param array|null $infos Informations completes (nomPrenom, service, email, etc.)
     */
    public function reserver(string $identifiant, ?array $infos = null): static
    {
        $this->reservePar = $identifiant;
        $this->reserveLe = new \DateTimeImmutable();

        if ($infos !== null) {
            $this->reserveParInfos = array_merge(['identifiant' => $identifiant], $infos);
        } else {
            $this->reserveParInfos = ['identifiant' => $identifiant];
        }

        return $this;
    }

    /**
     * Annule la reservation de l'operation
     */
    public function annulerReservation(): static
    {
        $this->reservePar = null;
        $this->reserveLe = null;
        $this->reserveParInfos = null;

        return $this;
    }

    /**
     * Informations completes sur la personne ayant reserve
     */
    public function getReserveParInfos(): ?array
    {
        return $this->reserveParInfos;
    }

    public function setReserveParInfos(?array $reserveParInfos): static
    {
        $this->reserveParInfos = $reserveParInfos;

        return $this;
    }

    /**
     * Retourne le nom complet de la personne ayant reserve (ou l'identifiant si non disponible)
     */
    public function getReserveParNomComplet(): ?string
    {
        return $this->reserveParInfos['nomPrenom'] ?? $this->reservePar;
    }

    /**
     * Retourne le service de la personne ayant reserve
     */
    public function getReserveParService(): ?string
    {
        return $this->reserveParInfos['service'] ?? null;
    }

    /**
     * Retourne l'email de la personne ayant reserve
     */
    public function getReserveParEmail(): ?string
    {
        return $this->reserveParInfos['email'] ?? null;
    }
}
