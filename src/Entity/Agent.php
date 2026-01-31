<?php

namespace App\Entity;

use App\Repository\AgentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite Agent pour OpsTracker V2 - Module Reservation.
 *
 * Un Agent est un utilisateur metier (distinct de Utilisateur IT)
 * qui peut reserver des creneaux d'intervention.
 *
 * Regles metier :
 * - RG-121 : Un agent = un seul creneau par campagne
 * - RG-124 : Manager ne voit que les agents de son service
 * - RG-125 : Tracabilite : enregistrer qui a positionne
 */
#[ORM\Entity(repositoryClass: AgentRepository::class)]
#[ORM\Table(name: 'agent')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['matricule'], message: 'Un agent existe deja avec ce matricule.')]
#[UniqueEntity(fields: ['email'], message: 'Un agent existe deja avec cet email.')]
class Agent
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le matricule est obligatoire.')]
    #[Assert\Length(min: 3, max: 50)]
    private ?string $matricule = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide.')]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prenom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $site = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeContrat = null;

    /**
     * Manager de l'agent (self-reference)
     * RG-124 : Manager ne voit que les agents de son service
     */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $manager = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    /**
     * Numero de telephone pour les notifications SMS (format E.164)
     * FINDING-004 : Validation stricte du format international
     */
    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\+[1-9]\d{6,14}$/',
        message: 'Le numero de telephone doit etre au format international E.164 (ex: +33612345678)'
    )]
    private ?string $telephone = null;

    /**
     * Opt-in pour recevoir les rappels par SMS
     */
    #[ORM\Column(type: 'boolean')]
    private bool $smsOptIn = false;

    /**
     * Token de reservation pour acces sans authentification.
     * Utilise pour l'interface agent (lien unique par email).
     */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $bookingToken = null;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'agent', cascade: ['persist'], orphanRemoval: true)]
    private Collection $reservations;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

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
        $this->matricule = strtoupper(trim($matricule));

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));

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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getNomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(?string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getTypeContrat(): ?string
    {
        return $this->typeContrat;
    }

    public function setTypeContrat(?string $typeContrat): static
    {
        $this->typeContrat = $typeContrat;

        return $this;
    }

    public function getManager(): ?self
    {
        return $this->manager;
    }

    public function setManager(?self $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    /**
     * Definit le numero de telephone en le normalisant au format E.164.
     *
     * FINDING-004 : Validation stricte avec normalisation et rejet des invalides.
     *
     * Formats acceptes :
     * - +33612345678 (E.164 international)
     * - 0612345678, 06 12 34 56 78, 06-12-34-56-78 (francais mobiles)
     * - 33612345678 (sans le +)
     *
     * @throws \InvalidArgumentException Si le format est invalide apres normalisation
     */
    public function setTelephone(?string $telephone): static
    {
        // Accepter null ou chaine vide
        if ($telephone === null || $telephone === '') {
            $this->telephone = null;

            return $this;
        }

        // Normaliser : supprimer espaces, tirets, points, parentheses
        $normalized = preg_replace('/[\s\-\.\(\)]/', '', $telephone);

        // Supprimer tous les caracteres non numeriques sauf le +
        $normalized = preg_replace('/[^0-9+]/', '', $normalized);

        // Convertir format francais mobile 06/07 → +33
        if (preg_match('/^0([67]\d{8})$/', $normalized, $matches)) {
            $normalized = '+33' . $matches[1];
        }

        // Ajouter + si commence par indicatif 33 sans +
        if (preg_match('/^33([67]\d{8})$/', $normalized, $matches)) {
            $normalized = '+33' . $matches[1];
        }

        // FINDING-004 : Valider format E.164 strict
        if (!preg_match('/^\+[1-9]\d{6,14}$/', $normalized)) {
            throw new \InvalidArgumentException(
                sprintf('Numero de telephone invalide: "%s". Format attendu: +33612345678 (E.164)', $telephone)
            );
        }

        $this->telephone = $normalized;

        return $this;
    }

    public function isSmsOptIn(): bool
    {
        return $this->smsOptIn;
    }

    public function setSmsOptIn(bool $smsOptIn): static
    {
        $this->smsOptIn = $smsOptIn;

        return $this;
    }

    /**
     * Verifie si l'agent peut recevoir des SMS (opt-in et telephone valide).
     */
    public function canReceiveSms(): bool
    {
        return $this->smsOptIn && !empty($this->telephone);
    }

    public function getBookingToken(): ?string
    {
        return $this->bookingToken;
    }

    public function setBookingToken(?string $bookingToken): static
    {
        $this->bookingToken = $bookingToken;

        return $this;
    }

    /**
     * Genere un nouveau token de reservation unique.
     */
    public function generateBookingToken(): string
    {
        $this->bookingToken = bin2hex(random_bytes(32));

        return $this->bookingToken;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setAgent($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getAgent() === $this) {
                $reservation->setAgent(null);
            }
        }

        return $this;
    }

    /**
     * Verifie si l'agent a deja une reservation pour une campagne donnee.
     * RG-121 : Un agent = un seul creneau par campagne
     */
    public function hasReservationPourCampagne(Campagne $campagne): bool
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->getCampagne() === $campagne && $reservation->getStatut() !== 'annulee') {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne la reservation active pour une campagne donnee.
     */
    public function getReservationPourCampagne(Campagne $campagne): ?Reservation
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->getCampagne() === $campagne && $reservation->getStatut() !== 'annulee') {
                return $reservation;
            }
        }

        return null;
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
        return $this->getNomComplet() ?: $this->matricule ?? '';
    }
}
