<?php

namespace App\Entity;

use App\Repository\ChecklistInstanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entite ChecklistInstance pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-033 : Persistance progression - chaque coche est sauvegardee immediatement
 *
 * Architecture retroactive :
 * - La structure de la checklist est lue depuis Campagne.checklistStructure
 * - Cette instance ne stocke que les IDs des etapes cochees (etapesCochees)
 * - Les modifications de la structure impactent toutes les operations immediatement
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
     * @deprecated Utiliser Campagne.checklistStructure a la place
     * Conserve pour retrocompatibilite pendant la migration
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $snapshot = null;

    /**
     * @deprecated Utiliser etapesCochees a la place
     * Conserve pour retrocompatibilite pendant la migration
     */
    #[ORM\Column(type: Types::JSON)]
    private array $progression = [];

    /**
     * RG-033 : Liste des etapes cochees (architecture retroactive)
     * Stocke uniquement les IDs des etapes cochees avec leurs metadonnees
     * Structure :
     * {
     *   "etape-1-1": {
     *     "dateCoche": "2026-01-22T10:30:00+00:00",
     *     "utilisateurId": 42
     *   }
     * }
     * Note: Si l'etape est decochee, elle est supprimee de ce tableau
     */
    #[ORM\Column(type: Types::JSON)]
    private array $etapesCochees = [];

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

    /**
     * @deprecated Utiliser Campagne.checklistStructure a la place
     */
    public function getSnapshot(): ?array
    {
        return $this->snapshot;
    }

    /**
     * @deprecated Utiliser Campagne.checklistStructure a la place
     */
    public function setSnapshot(?array $snapshot): static
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    /**
     * @deprecated Utiliser ChecklistService::copierTemplateVersCampagne() a la place
     */
    public function createSnapshotFromTemplate(ChecklistTemplate $template): static
    {
        $this->template = $template;
        $this->templateVersion = $template->getVersion();
        $this->snapshot = $template->getEtapes();

        return $this;
    }

    /**
     * @deprecated Utiliser Campagne.checklistStructure a la place
     * @return array<array>
     */
    public function getPhases(): array
    {
        return $this->snapshot['phases'] ?? [];
    }

    /**
     * @deprecated Utiliser getEtapesCochees() a la place
     */
    public function getProgression(): array
    {
        return $this->progression;
    }

    /**
     * @deprecated Utiliser setEtapesCochees() a la place
     */
    public function setProgression(array $progression): static
    {
        $this->progression = $progression;

        return $this;
    }

    /**
     * Retourne les etapes cochees avec leurs metadonnees
     */
    public function getEtapesCochees(): array
    {
        return $this->etapesCochees;
    }

    /**
     * Retourne uniquement les IDs des etapes cochees
     *
     * @return string[]
     */
    public function getEtapesCocheesIds(): array
    {
        return array_keys($this->etapesCochees);
    }

    public function setEtapesCochees(array $etapesCochees): static
    {
        $this->etapesCochees = $etapesCochees;

        return $this;
    }

    /**
     * RG-033 : Coche une etape (nouvelle architecture)
     */
    public function cocherEtape(string $etapeId, int $utilisateurId): static
    {
        // Nouvelle architecture : stocke dans etapesCochees
        $this->etapesCochees[$etapeId] = [
            'dateCoche' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'utilisateurId' => $utilisateurId,
        ];

        // Retrocompatibilite : met aussi a jour progression
        $this->progression[$etapeId] = [
            'cochee' => true,
            'dateCoche' => $this->etapesCochees[$etapeId]['dateCoche'],
            'utilisateurId' => $utilisateurId,
        ];

        return $this;
    }

    /**
     * RG-033 : Decoche une etape (nouvelle architecture)
     */
    public function decocherEtape(string $etapeId): static
    {
        // Nouvelle architecture : supprime de etapesCochees
        unset($this->etapesCochees[$etapeId]);

        // Retrocompatibilite : met aussi a jour progression
        if (isset($this->progression[$etapeId])) {
            $this->progression[$etapeId]['cochee'] = false;
            $this->progression[$etapeId]['dateDecoche'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        }

        return $this;
    }

    /**
     * Verifie si une etape est cochee (nouvelle architecture)
     */
    public function isEtapeCochee(string $etapeId): bool
    {
        // Nouvelle architecture : verifie dans etapesCochees
        if (isset($this->etapesCochees[$etapeId])) {
            return true;
        }

        // Fallback retrocompatibilite : verifie dans progression
        return ($this->progression[$etapeId]['cochee'] ?? false) === true;
    }

    /**
     * Compte le nombre d'etapes cochees
     */
    public function getNombreEtapesCochees(): int
    {
        // Nouvelle architecture
        if (!empty($this->etapesCochees)) {
            return count($this->etapesCochees);
        }

        // Fallback retrocompatibilite
        $count = 0;
        foreach ($this->progression as $etape) {
            if ($etape['cochee'] ?? false) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @deprecated Utiliser ChecklistService::getProgression() a la place
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
     * @deprecated Utiliser ChecklistService::getProgression() a la place
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
     * @deprecated Utiliser ChecklistService::getProgression() a la place
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
     * @deprecated Utiliser ChecklistService::getProgression() a la place
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
     * @deprecated Utiliser ChecklistService::getProgression() a la place
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
