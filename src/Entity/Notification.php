<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite Notification pour OpsTracker V2 - Module Reservation.
 *
 * Historique des emails envoyes aux agents concernant leurs reservations.
 *
 * Regles metier :
 * - RG-140 : Email confirmation contient ICS obligatoire
 * - RG-141 : Email rappel automatique J-X
 * - RG-142 : Email modification = ancien + nouveau + ICS
 * - RG-143 : Email annulation = lien repositionnement
 * - RG-144 : Invitation selon mode (agent/manager)
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    // Types de notification
    public const TYPE_CONFIRMATION = 'confirmation';
    public const TYPE_RAPPEL = 'rappel';
    public const TYPE_MODIFICATION = 'modification';
    public const TYPE_ANNULATION = 'annulation';
    public const TYPE_INVITATION = 'invitation';

    public const TYPES = [
        self::TYPE_CONFIRMATION => 'Confirmation',
        self::TYPE_RAPPEL => 'Rappel',
        self::TYPE_MODIFICATION => 'Modification',
        self::TYPE_ANNULATION => 'Annulation',
        self::TYPE_INVITATION => 'Invitation',
    ];

    // Statuts d'envoi
    public const STATUT_PENDING = 'pending';
    public const STATUT_SENT = 'sent';
    public const STATUT_FAILED = 'failed';

    public const STATUTS = [
        self::STATUT_PENDING => 'En attente',
        self::STATUT_SENT => 'Envoye',
        self::STATUT_FAILED => 'Echec',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agent::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Agent $agent = null;

    /**
     * Reservation associee (nullable pour les invitations initiales)
     */
    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Reservation $reservation = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private string $type = self::TYPE_CONFIRMATION;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le sujet est obligatoire.')]
    private string $sujet = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    private string $contenu = '';

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_PENDING;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    /**
     * Message d'erreur en cas d'echec d'envoi
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!array_key_exists($type, self::TYPES)) {
            throw new \InvalidArgumentException(sprintf('Type de notification invalide : %s', $type));
        }
        $this->type = $type;

        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getSujet(): string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = $sujet;

        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

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

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * Marque la notification comme envoyee.
     */
    public function markAsSent(): static
    {
        $this->statut = self::STATUT_SENT;
        $this->sentAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Marque la notification comme echouee.
     */
    public function markAsFailed(string $errorMessage): static
    {
        $this->statut = self::STATUT_FAILED;
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * Verifie si la notification est en attente.
     */
    public function isPending(): bool
    {
        return $this->statut === self::STATUT_PENDING;
    }

    /**
     * Verifie si la notification a ete envoyee.
     */
    public function isSent(): bool
    {
        return $this->statut === self::STATUT_SENT;
    }

    /**
     * Verifie si l'envoi a echoue.
     */
    public function isFailed(): bool
    {
        return $this->statut === self::STATUT_FAILED;
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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s', $this->getTypeLabel(), $this->sujet);
    }
}
