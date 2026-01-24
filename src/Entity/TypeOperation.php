<?php

namespace App\Entity;

use App\Repository\TypeOperationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entite TypeOperation pour OpsTracker.
 *
 * Regles metier implementees :
 * - RG-060 : Type = Nom + Description + Icone + Couleur + Champs JSONB
 * - RG-061 : Types de champs personnalises (V1) : Texte court, Texte long, Nombre, Date, Liste
 */
#[ORM\Entity(repositoryClass: TypeOperationRepository::class)]
#[ORM\Table(name: 'type_operation')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['nom'], message: 'Un type d\'operation existe deja avec ce nom.')]
class TypeOperation
{
    // Couleurs disponibles (design system Bauhaus)
    public const COULEURS = [
        'primary' => 'Bleu',
        'success' => 'Vert',
        'warning' => 'Orange',
        'danger' => 'Rouge',
        'complete' => 'Teal',
        'muted' => 'Gris',
    ];

    // Icones disponibles (Lucide icons)
    public const ICONES = [
        'monitor' => 'Moniteur',
        'laptop' => 'Laptop',
        'server' => 'Serveur',
        'printer' => 'Imprimante',
        'network' => 'Reseau',
        'hard-drive' => 'Disque dur',
        'cpu' => 'CPU',
        'smartphone' => 'Smartphone',
        'settings' => 'Parametres',
        'wrench' => 'Outil',
        'refresh-cw' => 'Migration',
        'download' => 'Installation',
        'upload' => 'Deploiement',
        'shield' => 'Securite',
        'users' => 'Utilisateurs',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le nom du type est obligatoire.')]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'L\'icone est obligatoire.')]
    private string $icone = 'settings';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'La couleur est obligatoire.')]
    private string $couleur = 'primary';

    /**
     * Configuration des champs personnalises (RG-061)
     * Structure JSONB :
     * [
     *   {
     *     "code": "numero_inventaire",
     *     "label": "Numero d'inventaire",
     *     "type": "text_court",
     *     "obligatoire": true,
     *     "options": []
     *   },
     *   ...
     * ]
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $champsPersonnalises = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    /**
     * RG-132 : Duree estimee en minutes pour ce type d'operation (abaques)
     */
    #[ORM\Column(nullable: true)]
    private ?int $dureeEstimeeMinutes = null;

    /** @var Collection<int, Campagne> */
    #[ORM\OneToMany(targetEntity: Campagne::class, mappedBy: 'typeOperation')]
    private Collection $campagnes;

    /** @var Collection<int, Operation> */
    #[ORM\OneToMany(targetEntity: Operation::class, mappedBy: 'typeOperation')]
    private Collection $operations;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->campagnes = new ArrayCollection();
        $this->operations = new ArrayCollection();
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

    public function getIcone(): string
    {
        return $this->icone;
    }

    public function setIcone(string $icone): static
    {
        $this->icone = $icone;

        return $this;
    }

    public function getIconeLabel(): string
    {
        return self::ICONES[$this->icone] ?? $this->icone;
    }

    public function getCouleur(): string
    {
        return $this->couleur;
    }

    public function setCouleur(string $couleur): static
    {
        $this->couleur = $couleur;

        return $this;
    }

    public function getCouleurLabel(): string
    {
        return self::COULEURS[$this->couleur] ?? $this->couleur;
    }

    public function getChampsPersonnalises(): ?array
    {
        return $this->champsPersonnalises;
    }

    public function setChampsPersonnalises(?array $champsPersonnalises): static
    {
        $this->champsPersonnalises = $champsPersonnalises;

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

    /**
     * RG-132 : Duree estimee en minutes
     */
    public function getDureeEstimeeMinutes(): ?int
    {
        return $this->dureeEstimeeMinutes;
    }

    public function setDureeEstimeeMinutes(?int $dureeEstimeeMinutes): static
    {
        $this->dureeEstimeeMinutes = $dureeEstimeeMinutes;

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
     * @return Collection<int, Operation>
     */
    public function getOperations(): Collection
    {
        return $this->operations;
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
        return $this->nom ?? '';
    }
}
