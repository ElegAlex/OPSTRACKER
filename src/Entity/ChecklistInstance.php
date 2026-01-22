<?php

namespace App\Entity;

use App\Repository\ChecklistInstanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite ChecklistInstance pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-031 : Snapshot Pattern - l'instance conserve une copie du template
 *            Les modifications du template n'affectent pas les instances existantes
 * - RG-033 : Persistance progression - chaque coche est sauvegardee immediatement
 */
#[ORM\Entity(repositoryClass: ChecklistInstanceRepository::class)]
#[ORM\Table(name: 'checklist_instance')]
#[ORM\HasLifecycleCallbacks]
class ChecklistInstance
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Reference au template d'origine (pour traçabilite)
     */
    #[ORM\ManyToOne(targetEntity: ChecklistTemplate::class, inversedBy: 'instances')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ChecklistTemplate $template = null;

    /**
     * Version du template au moment de la creation
     */
    #[ORM\Column(type: 'integer')]
    private int $templateVersion = 1;

    /**
     * RG-031 : Snapshot du template au moment de la creation
     * Copie complete de la structure des etapes
     */
    #[ORM\Column(type: Types::JSON)]
    private array $snapshot = ['phases' => []];

    /**
     * RG-033 : Progression des etapes cochees
     * Structure :
     * {
     *   "etape-1-1": {
     *     "cochee": true,
     *     "dateCoche": "2026-01-22T10:30:00+00:00",
     *     "utilisateurId": 42
     *   }
     * }
     */
    #[ORM\Column(type: Types::JSON)]
    private array $progression = [];

    #[ORM\OneToOne(targetEntity: Operation::class, inversedBy: 'checklistInstance')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Operation $operation = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplate(): ?ChecklistTemplate
    {
        return $this->template;
    }

    public function setTemplate(?ChecklistTemplate $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplateVersion(): int
    {
        return $this->templateVersion;
    }

    public function setTemplateVersion(int $templateVersion): static
    {
        $this->templateVersion = $templateVersion;

        return $this;
    }

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function setSnapshot(array $snapshot): static
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    /**
     * RG-031 : Cree un snapshot a partir d'un template
     */
    public function createSnapshotFromTemplate(ChecklistTemplate $template): static
    {
        $this->template = $template;
        $this->templateVersion = $template->getVersion();
        $this->snapshot = $template->getEtapes();

        return $this;
    }

    /**
     * Retourne les phases du snapshot
     *
     * @return array<array>
     */
    public function getPhases(): array
    {
        return $this->snapshot['phases'] ?? [];
    }

    public function getProgression(): array
    {
        return $this->progression;
    }

    public function setProgression(array $progression): static
    {
        $this->progression = $progression;

        return $this;
    }

    /**
     * RG-033 : Coche une etape
     */
    public function cocherEtape(string $etapeId, int $utilisateurId): static
    {
        $this->progression[$etapeId] = [
            'cochee' => true,
            'dateCoche' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'utilisateurId' => $utilisateurId,
        ];

        return $this;
    }

    /**
     * RG-033 : Decoche une etape
     */
    public function decocherEtape(string $etapeId): static
    {
        if (isset($this->progression[$etapeId])) {
            $this->progression[$etapeId]['cochee'] = false;
            $this->progression[$etapeId]['dateDecoche'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        }

        return $this;
    }

    /**
     * Verifie si une etape est cochee
     */
    public function isEtapeCochee(string $etapeId): bool
    {
        return ($this->progression[$etapeId]['cochee'] ?? false) === true;
    }

    /**
     * Compte le nombre d'etapes cochees
     */
    public function getNombreEtapesCochees(): int
    {
        $count = 0;
        foreach ($this->progression as $etape) {
            if ($etape['cochee'] ?? false) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Compte le nombre total d'etapes
     */
    public function getNombreTotalEtapes(): int
    {
        $count = 0;
        foreach ($this->getPhases() as $phase) {
            $count += count($phase['etapes'] ?? []);
        }

        return $count;
    }

    /**
     * Calcule le pourcentage de progression
     */
    public function getPourcentageProgression(): float
    {
        $total = $this->getNombreTotalEtapes();
        if ($total === 0) {
            return 100.0;
        }

        return round(($this->getNombreEtapesCochees() / $total) * 100, 1);
    }

    /**
     * Verifie si toutes les etapes obligatoires sont cochees
     */
    public function isComplete(): bool
    {
        foreach ($this->getPhases() as $phase) {
            foreach ($phase['etapes'] ?? [] as $etape) {
                if (($etape['obligatoire'] ?? true) && !$this->isEtapeCochee($etape['id'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * RG-032 : Verifie si une phase est deverrouillee
     * Une phase est deverrouillee si la phase precedente est complete
     */
    public function isPhaseAccessible(string $phaseId): bool
    {
        $phases = $this->getPhases();
        $previousPhaseComplete = true;

        foreach ($phases as $phase) {
            if ($phase['id'] === $phaseId) {
                return $previousPhaseComplete;
            }

            // Verifier si la phase precedente est verrouillable
            if ($phase['verrouillable'] ?? false) {
                $previousPhaseComplete = $this->isPhaseComplete($phase['id']);
            }
        }

        return false;
    }

    /**
     * Verifie si toutes les etapes obligatoires d'une phase sont cochees
     */
    public function isPhaseComplete(string $phaseId): bool
    {
        foreach ($this->getPhases() as $phase) {
            if ($phase['id'] === $phaseId) {
                foreach ($phase['etapes'] ?? [] as $etape) {
                    if (($etape['obligatoire'] ?? true) && !$this->isEtapeCochee($etape['id'])) {
                        return false;
                    }
                }

                return true;
            }
        }

        return false;
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
        return sprintf('Checklist #%d (%d%%)', $this->id ?? 0, (int) $this->getPourcentageProgression());
    }
}
