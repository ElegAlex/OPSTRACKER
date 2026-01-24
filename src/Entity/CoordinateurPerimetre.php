<?php

namespace App\Entity;

use App\Repository\CoordinateurPerimetreRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite CoordinateurPerimetre pour OpsTracker V2.
 *
 * Definit le perimetre de delegation d'un coordinateur.
 * RG-114 : Coordinateur peut positionner des agents sans lien hierarchique.
 */
#[ORM\Entity(repositoryClass: CoordinateurPerimetreRepository::class)]
#[ORM\Table(name: 'coordinateur_perimetre')]
#[ORM\UniqueConstraint(name: 'unique_coordinateur_service', columns: ['coordinateur_id', 'service'])]
#[ORM\HasLifecycleCallbacks]
class CoordinateurPerimetre
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * L'utilisateur coordinateur
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $coordinateur = null;

    /**
     * Le service delegue au coordinateur
     */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le service est obligatoire.')]
    private ?string $service = null;

    /**
     * Site optionnel pour limiter davantage le perimetre
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $site = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCoordinateur(): ?Utilisateur
    {
        return $this->coordinateur;
    }

    public function setCoordinateur(?Utilisateur $coordinateur): static
    {
        $this->coordinateur = $coordinateur;

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(string $service): static
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
        $label = $this->service ?? '';
        if ($this->site) {
            $label .= ' (' . $this->site . ')';
        }

        return $label;
    }
}
