<?php

namespace App\Entity;

use App\Repository\ReservationEndUserRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Reservation d'un end-user sur une operation.
 *
 * Permet les reservations multiples si operation.capacite > 1.
 */
#[ORM\Entity(repositoryClass: ReservationEndUserRepository::class)]
#[ORM\Table(name: 'reservation_end_user')]
#[ORM\Index(columns: ['identifiant'], name: 'idx_reservation_identifiant')]
class ReservationEndUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Operation::class, inversedBy: 'reservationsEndUser')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Operation $operation = null;

    #[ORM\Column(length: 255)]
    private ?string $identifiant = null;

    #[ORM\Column(length: 255)]
    private ?string $nomPrenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $site = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $reserveLe = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $smsOptIn = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $icsEnvoye = false;

    public function __construct()
    {
        $this->reserveLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOperation(): ?Operation
    {
        return $this->operation;
    }

    public function setOperation(?Operation $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    public function getIdentifiant(): ?string
    {
        return $this->identifiant;
    }

    public function setIdentifiant(string $identifiant): static
    {
        $this->identifiant = $identifiant;

        return $this;
    }

    public function getNomPrenom(): ?string
    {
        return $this->nomPrenom;
    }

    public function setNomPrenom(string $nomPrenom): static
    {
        $this->nomPrenom = $nomPrenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
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

    public function getReserveLe(): ?\DateTimeImmutable
    {
        return $this->reserveLe;
    }

    public function setReserveLe(\DateTimeImmutable $reserveLe): static
    {
        $this->reserveLe = $reserveLe;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

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

    public function isIcsEnvoye(): bool
    {
        return $this->icsEnvoye;
    }

    public function setIcsEnvoye(bool $icsEnvoye): static
    {
        $this->icsEnvoye = $icsEnvoye;

        return $this;
    }

    /**
     * Retourne le nom de la campagne associee.
     */
    public function getCampagneNom(): ?string
    {
        return $this->operation?->getCampagne()?->getNom();
    }
}
