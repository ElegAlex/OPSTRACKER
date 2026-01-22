<?php

namespace App\Entity;

use App\Repository\ChecklistTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite ChecklistTemplate pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-030 : Template = Nom + Version + Etapes ordonnees + Phases optionnelles
 * - RG-031 : Snapshot Pattern (voir ChecklistInstance)
 * - RG-032 : Phases verrouillables (V1)
 */
#[ORM\Entity(repositoryClass: ChecklistTemplateRepository::class)]
#[ORM\Table(name: 'checklist_template')]
#[ORM\HasLifecycleCallbacks]
class ChecklistTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du template est obligatoire.')]
    #[Assert\Length(min: 3, max: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Numero de version du template (incremente a chaque modification)
     * RG-031 : Nouvelle version a chaque modification
     */
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /**
     * Structure des etapes en JSON
     * RG-030 : Etapes ordonnees + Phases optionnelles
     *
     * Structure :
     * {
     *   "phases": [
     *     {
     *       "id": "phase-1",
     *       "nom": "Preparation",
     *       "ordre": 1,
     *       "verrouillable": true,
     *       "etapes": [
     *         {
     *           "id": "etape-1-1",
     *           "titre": "Verifier le materiel",
     *           "description": "S'assurer que tous les composants sont presents",
     *           "ordre": 1,
     *           "obligatoire": true,
     *           "documentId": null
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'La structure des etapes est obligatoire.')]
    private array $etapes = ['phases' => []];

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    /** @var Collection<int, Campagne> */
    #[ORM\OneToMany(targetEntity: Campagne::class, mappedBy: 'checklistTemplate')]
    private Collection $campagnes;

    /** @var Collection<int, ChecklistInstance> */
    #[ORM\OneToMany(targetEntity: ChecklistInstance::class, mappedBy: 'template')]
    private Collection $instances;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->campagnes = new ArrayCollection();
        $this->instances = new ArrayCollection();
    }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Incremente la version (RG-031)
     */
    public function incrementVersion(): static
    {
        $this->version++;

        return $this;
    }

    public function getEtapes(): array
    {
        return $this->etapes;
    }

    public function setEtapes(array $etapes): static
    {
        $this->etapes = $etapes;

        return $this;
    }

    /**
     * Retourne les phases du template
     *
     * @return array<array>
     */
    public function getPhases(): array
    {
        return $this->etapes['phases'] ?? [];
    }

    /**
     * Ajoute une phase au template
     */
    public function addPhase(string $id, string $nom, int $ordre = 0, bool $verrouillable = false): static
    {
        $this->etapes['phases'][] = [
            'id' => $id,
            'nom' => $nom,
            'ordre' => $ordre,
            'verrouillable' => $verrouillable,
            'etapes' => [],
        ];

        return $this;
    }

    /**
     * Ajoute une etape a une phase
     */
    public function addEtapeToPhase(
        string $phaseId,
        string $etapeId,
        string $titre,
        ?string $description = null,
        int $ordre = 0,
        bool $obligatoire = true,
        ?int $documentId = null
    ): static {
        foreach ($this->etapes['phases'] as $key => $phase) {
            if ($phase['id'] === $phaseId) {
                $this->etapes['phases'][$key]['etapes'][] = [
                    'id' => $etapeId,
                    'titre' => $titre,
                    'description' => $description,
                    'ordre' => $ordre,
                    'obligatoire' => $obligatoire,
                    'documentId' => $documentId,
                ];
                break;
            }
        }

        return $this;
    }

    /**
     * Compte le nombre total d'etapes
     */
    public function getNombreEtapes(): int
    {
        $count = 0;
        foreach ($this->getPhases() as $phase) {
            $count += count($phase['etapes'] ?? []);
        }

        return $count;
    }

    /**
     * Compte le nombre d'etapes obligatoires
     */
    public function getNombreEtapesObligatoires(): int
    {
        $count = 0;
        foreach ($this->getPhases() as $phase) {
            foreach ($phase['etapes'] ?? [] as $etape) {
                if ($etape['obligatoire'] ?? true) {
                    $count++;
                }
            }
        }

        return $count;
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

    /**
     * @return Collection<int, Campagne>
     */
    public function getCampagnes(): Collection
    {
        return $this->campagnes;
    }

    /**
     * @return Collection<int, ChecklistInstance>
     */
    public function getInstances(): Collection
    {
        return $this->instances;
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
        return sprintf('%s (v%d)', $this->nom, $this->version);
    }
}
