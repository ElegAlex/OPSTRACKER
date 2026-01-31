<?php

namespace App\Entity;

use App\Repository\CampagneAgentAutoriseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Liste des personnes autorisees a reserver pour une campagne (mode import).
 *
 * Cette entite stocke les agents importes via CSV specifique a une campagne.
 * Contrairement a la table Agent (annuaire global), cette liste est ponctuelle
 * et propre a chaque campagne.
 */
#[ORM\Entity(repositoryClass: CampagneAgentAutoriseRepository::class)]
#[ORM\Table(name: 'campagne_agent_autorise')]
#[ORM\UniqueConstraint(columns: ['campagne_id', 'identifiant'])]
#[ORM\HasLifecycleCallbacks]
class CampagneAgentAutorise
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Campagne::class, inversedBy: 'agentsAutorises')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Campagne $campagne = null;

    /**
     * Identifiant unique pour cette personne (email, matricule, ou nom_prenom).
     * Sert de cle pour la selection dans le dropdown.
     */
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

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

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
        return $this->nomPrenom ?? $this->identifiant ?? '';
    }
}
