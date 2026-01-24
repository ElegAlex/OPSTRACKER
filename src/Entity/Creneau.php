<?php

namespace App\Entity;

use App\Repository\CreneauRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite Creneau pour OpsTracker V2 - Module Reservation.
 *
 * Un Creneau est une plage horaire reservable pour une campagne.
 *
 * Regles metier :
 * - RG-123 : Verrouillage J-X (defaut: 2 jours avant)
 * - RG-130 : Creation creneaux manuelle ou generation automatique
 * - RG-135 : Association creneau <-> segment optionnelle
 */
#[ORM\Entity(repositoryClass: CreneauRepository::class)]
#[ORM\Table(name: 'creneau')]
#[ORM\HasLifecycleCallbacks]
class Creneau
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Campagne::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Campagne $campagne = null;

    /**
     * RG-135 : Association segment optionnelle
     */
    #[ORM\ManyToOne(targetEntity: Segment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Segment $segment = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date est obligatoire.')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank(message: 'L\'heure de debut est obligatoire.')]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank(message: 'L\'heure de fin est obligatoire.')]
    private ?\DateTimeInterface $heureFin = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive(message: 'La capacite doit etre positive.')]
    private int $capacite = 1;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieu = null;

    /**
     * RG-123 : Verrouillage manuel du creneau
     */
    #[ORM\Column(type: 'boolean')]
    private bool $verrouille = false;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'creneau', cascade: ['persist'], orphanRemoval: true)]
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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getHeureDebut(): ?\DateTimeInterface
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(\DateTimeInterface $heureDebut): static
    {
        $this->heureDebut = $heureDebut;

        return $this;
    }

    public function getHeureFin(): ?\DateTimeInterface
    {
        return $this->heureFin;
    }

    public function setHeureFin(\DateTimeInterface $heureFin): static
    {
        $this->heureFin = $heureFin;

        return $this;
    }

    public function getCapacite(): int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): static
    {
        $this->capacite = $capacite;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function isVerrouille(): bool
    {
        return $this->verrouille;
    }

    public function setVerrouille(bool $verrouille): static
    {
        $this->verrouille = $verrouille;

        return $this;
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
            $reservation->setCreneau($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getCreneau() === $this) {
                $reservation->setCreneau(null);
            }
        }

        return $this;
    }

    /**
     * Calcule le nombre de places restantes.
     * Seules les reservations confirmees comptent.
     */
    public function getPlacesRestantes(): int
    {
        $reservationsActives = $this->reservations->filter(
            fn (Reservation $r) => $r->getStatut() === 'confirmee'
        );

        return max(0, $this->capacite - $reservationsActives->count());
    }

    /**
     * Verifie si le creneau est complet.
     */
    public function isComplet(): bool
    {
        return $this->getPlacesRestantes() <= 0;
    }

    /**
     * Verifie si le creneau est verrouille (manuellement ou par date).
     * RG-123 : Verrouillage J-X (defaut: 2 jours avant)
     */
    public function isVerrouillePourDate(int $joursAvant = 2): bool
    {
        if ($this->verrouille) {
            return true;
        }

        $dateLimite = (new \DateTime())->modify("+{$joursAvant} days");

        return $this->date <= $dateLimite;
    }

    /**
     * Retourne le taux de remplissage en pourcentage.
     */
    public function getTauxRemplissage(): int
    {
        if ($this->capacite === 0) {
            return 0;
        }

        $reservationsActives = $this->reservations->filter(
            fn (Reservation $r) => $r->getStatut() === 'confirmee'
        );

        return (int) round(($reservationsActives->count() / $this->capacite) * 100);
    }

    /**
     * Retourne la couleur du taux de remplissage.
     * Vert <50%, Orange 50-90%, Rouge >90%
     */
    public function getCouleurRemplissage(): string
    {
        $taux = $this->getTauxRemplissage();

        if ($taux >= 90) {
            return 'danger';
        }
        if ($taux >= 50) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Retourne la duree du creneau en minutes.
     */
    public function getDureeMinutes(): int
    {
        if (!$this->heureDebut || !$this->heureFin) {
            return 0;
        }

        $debut = \DateTime::createFromInterface($this->heureDebut);
        $fin = \DateTime::createFromInterface($this->heureFin);

        return (int) (($fin->getTimestamp() - $debut->getTimestamp()) / 60);
    }

    /**
     * Retourne le label du creneau (date + heures).
     */
    public function getLabel(): string
    {
        if (!$this->date || !$this->heureDebut || !$this->heureFin) {
            return '';
        }

        return sprintf(
            '%s de %s a %s',
            $this->date->format('d/m/Y'),
            $this->heureDebut->format('H:i'),
            $this->heureFin->format('H:i')
        );
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
        return $this->getLabel();
    }
}
