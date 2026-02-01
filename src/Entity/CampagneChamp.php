<?php

namespace App\Entity;

use App\Repository\CampagneChampRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite CampagneChamp pour OpsTracker.
 *
 * Represente une colonne/champ dynamique d'une campagne.
 * Permet de definir le schema des donnees avant import CSV.
 */
#[ORM\Entity(repositoryClass: CampagneChampRepository::class)]
#[ORM\Table(name: 'campagne_champ')]
#[ORM\UniqueConstraint(name: 'unique_champ_nom_campagne', columns: ['nom', 'campagne_id'])]
class CampagneChamp
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du champ est obligatoire.')]
    #[Assert\Length(min: 1, max: 100)]
    private ?string $nom = null;

    #[ORM\ManyToOne(targetEntity: Campagne::class, inversedBy: 'champs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Campagne $campagne = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $ordre = null;

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

    public function getCampagne(): ?Campagne
    {
        return $this->campagne;
    }

    public function setCampagne(?Campagne $campagne): static
    {
        $this->campagne = $campagne;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
