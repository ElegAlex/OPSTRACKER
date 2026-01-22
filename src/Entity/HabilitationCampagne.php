<?php

namespace App\Entity;

use App\Repository\HabilitationCampagneRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite HabilitationCampagne pour les droits granulaires (RG-115).
 *
 * Droits disponibles :
 * - VOIR : Voir la campagne et ses operations
 * - POSITIONNER : Positionner des agents sur des creneaux
 * - CONFIGURER : Modifier la configuration de la campagne
 * - EXPORTER : Exporter les donnees de la campagne
 */
#[ORM\Entity(repositoryClass: HabilitationCampagneRepository::class)]
#[ORM\Table(name: 'habilitation_campagne')]
#[ORM\UniqueConstraint(name: 'unique_habilitation', columns: ['campagne_id', 'utilisateur_id'])]
class HabilitationCampagne
{
    // Droits disponibles (RG-115)
    public const DROIT_VOIR = 'voir';
    public const DROIT_POSITIONNER = 'positionner';
    public const DROIT_CONFIGURER = 'configurer';
    public const DROIT_EXPORTER = 'exporter';

    public const DROITS = [
        self::DROIT_VOIR => 'Voir la campagne',
        self::DROIT_POSITIONNER => 'Positionner des agents',
        self::DROIT_CONFIGURER => 'Modifier la configuration',
        self::DROIT_EXPORTER => 'Exporter les données',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Campagne::class, inversedBy: 'habilitations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Campagne $campagne = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'boolean')]
    private bool $peutVoir = true;

    #[ORM\Column(type: 'boolean')]
    private bool $peutPositionner = false;

    #[ORM\Column(type: 'boolean')]
    private bool $peutConfigurer = false;

    #[ORM\Column(type: 'boolean')]
    private bool $peutExporter = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function peutVoir(): bool
    {
        return $this->peutVoir;
    }

    public function setPeutVoir(bool $peutVoir): static
    {
        $this->peutVoir = $peutVoir;

        return $this;
    }

    public function peutPositionner(): bool
    {
        return $this->peutPositionner;
    }

    public function setPeutPositionner(bool $peutPositionner): static
    {
        $this->peutPositionner = $peutPositionner;

        return $this;
    }

    public function peutConfigurer(): bool
    {
        return $this->peutConfigurer;
    }

    public function setPeutConfigurer(bool $peutConfigurer): static
    {
        $this->peutConfigurer = $peutConfigurer;

        return $this;
    }

    public function peutExporter(): bool
    {
        return $this->peutExporter;
    }

    public function setPeutExporter(bool $peutExporter): static
    {
        $this->peutExporter = $peutExporter;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Retourne les droits sous forme de tableau.
     *
     * @return array<string>
     */
    public function getDroits(): array
    {
        $droits = [];

        if ($this->peutVoir) {
            $droits[] = self::DROIT_VOIR;
        }
        if ($this->peutPositionner) {
            $droits[] = self::DROIT_POSITIONNER;
        }
        if ($this->peutConfigurer) {
            $droits[] = self::DROIT_CONFIGURER;
        }
        if ($this->peutExporter) {
            $droits[] = self::DROIT_EXPORTER;
        }

        return $droits;
    }

    /**
     * Definit les droits depuis un tableau.
     *
     * @param array<string> $droits
     */
    public function setDroits(array $droits): static
    {
        $this->peutVoir = in_array(self::DROIT_VOIR, $droits, true);
        $this->peutPositionner = in_array(self::DROIT_POSITIONNER, $droits, true);
        $this->peutConfigurer = in_array(self::DROIT_CONFIGURER, $droits, true);
        $this->peutExporter = in_array(self::DROIT_EXPORTER, $droits, true);

        return $this;
    }

    /**
     * Retourne un resume des droits pour affichage.
     */
    public function getDroitsLabel(): string
    {
        $labels = [];

        if ($this->peutVoir) {
            $labels[] = 'Voir';
        }
        if ($this->peutPositionner) {
            $labels[] = 'Positionner';
        }
        if ($this->peutConfigurer) {
            $labels[] = 'Configurer';
        }
        if ($this->peutExporter) {
            $labels[] = 'Exporter';
        }

        return implode(', ', $labels) ?: 'Aucun droit';
    }
}
