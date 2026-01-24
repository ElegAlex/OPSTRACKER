<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite Reservation pour OpsTracker V2 - Module Reservation.
 *
 * Une Reservation associe un Agent a un Creneau pour une Campagne.
 *
 * Regles metier :
 * - RG-121 : Un agent = un seul creneau par campagne (contrainte unique)
 * - RG-125 : Tracabilite : enregistrer qui a positionne (agent/manager/coordinateur)
 * - RG-122 : Confirmation automatique = email + ICS
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
#[ORM\UniqueConstraint(name: 'unique_agent_campagne', columns: ['agent_id', 'campagne_id'])]
#[ORM\HasLifecycleCallbacks]
class Reservation
{
    // Types de positionnement (RG-125)
    public const TYPE_AGENT = 'agent';
    public const TYPE_MANAGER = 'manager';
    public const TYPE_COORDINATEUR = 'coordinateur';

    public const TYPES_POSITIONNEMENT = [
        self::TYPE_AGENT => 'Par l\'agent',
        self::TYPE_MANAGER => 'Par le manager',
        self::TYPE_COORDINATEUR => 'Par le coordinateur',
    ];

    // Statuts de reservation
    public const STATUT_CONFIRMEE = 'confirmee';
    public const STATUT_ANNULEE = 'annulee';

    public const STATUTS = [
        self::STATUT_CONFIRMEE => 'Confirmee',
        self::STATUT_ANNULEE => 'Annulee',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agent::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Agent $agent = null;

    #[ORM\ManyToOne(targetEntity: Creneau::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Creneau $creneau = null;

    #[ORM\ManyToOne(targetEntity: Campagne::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Campagne $campagne = null;

    /**
     * RG-125 : Type de positionnement (agent, manager, coordinateur)
     */
    #[ORM\Column(length: 20)]
    private string $typePositionnement = self::TYPE_AGENT;

    /**
     * RG-125 : Utilisateur IT qui a positionne l'agent (si manager ou coordinateur)
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $positionnePar = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_CONFIRMEE;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(?Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getCreneau(): ?Creneau
    {
        return $this->creneau;
    }

    public function setCreneau(?Creneau $creneau): static
    {
        $this->creneau = $creneau;

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

    public function getTypePositionnement(): string
    {
        return $this->typePositionnement;
    }

    public function setTypePositionnement(string $typePositionnement): static
    {
        if (!array_key_exists($typePositionnement, self::TYPES_POSITIONNEMENT)) {
            throw new \InvalidArgumentException(sprintf('Type de positionnement invalide : %s', $typePositionnement));
        }
        $this->typePositionnement = $typePositionnement;

        return $this;
    }

    public function getTypePositionnementLabel(): string
    {
        return self::TYPES_POSITIONNEMENT[$this->typePositionnement] ?? $this->typePositionnement;
    }

    public function getPositionnePar(): ?Utilisateur
    {
        return $this->positionnePar;
    }

    public function setPositionnePar(?Utilisateur $positionnePar): static
    {
        $this->positionnePar = $positionnePar;

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

    /**
     * Verifie si la reservation est confirmee.
     */
    public function isConfirmee(): bool
    {
        return $this->statut === self::STATUT_CONFIRMEE;
    }

    /**
     * Verifie si la reservation est annulee.
     */
    public function isAnnulee(): bool
    {
        return $this->statut === self::STATUT_ANNULEE;
    }

    /**
     * Annule la reservation.
     */
    public function annuler(): static
    {
        $this->statut = self::STATUT_ANNULEE;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Verifie si la reservation a ete positionnee par un tiers (manager ou coordinateur).
     */
    public function isPositionneParTiers(): bool
    {
        return $this->typePositionnement !== self::TYPE_AGENT;
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
        $agentNom = $this->agent ? $this->agent->getNomComplet() : '';
        $creneauLabel = $this->creneau ? $this->creneau->getLabel() : '';

        return sprintf('%s - %s', $agentNom, $creneauLabel);
    }
}
