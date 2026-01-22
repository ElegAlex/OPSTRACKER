<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Entity\Utilisateur;
use App\Repository\OperationRepository;
use App\Repository\SegmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Service metier pour la gestion des operations.
 *
 * Regles metier implementees :
 * - RG-017 : Gestion des 6 statuts operation avec workflow
 * - RG-018 : 1 operation = 1 technicien assigne maximum
 * - RG-021 : Motif de report optionnel
 * - RG-080 : Triple signalisation (icone + couleur + texte)
 */
class OperationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OperationRepository $operationRepository,
        private readonly SegmentRepository $segmentRepository,
        #[Target('operation')]
        private readonly WorkflowInterface $operationWorkflow,
    ) {
    }

    /**
     * Recupere les operations d'une campagne avec filtres optionnels.
     *
     * @param array{
     *     statut?: string|null,
     *     segment_id?: int|null,
     *     technicien_id?: int|null,
     *     search?: string|null
     * } $filtres
     * @return Operation[]
     */
    public function getOperationsWithFilters(Campagne $campagne, array $filtres = []): array
    {
        return $this->operationRepository->findWithFilters(
            $campagne->getId(),
            $filtres['statut'] ?? null,
            $filtres['segment_id'] ?? null,
            $filtres['technicien_id'] ?? null,
            $filtres['search'] ?? null
        );
    }

    /**
     * Applique une transition de workflow a l'operation.
     * RG-017 : Transitions validees par le workflow Symfony
     *
     * @param string|null $motif Motif de report (RG-021)
     */
    public function appliquerTransition(Operation $operation, string $transition, ?string $motif = null): bool
    {
        if (!$this->operationWorkflow->can($operation, $transition)) {
            return false;
        }

        // Si c'est un report, enregistrer le motif (RG-021)
        if ($transition === 'reporter' && $motif !== null) {
            $operation->setMotifReport($motif);
        }

        // Si c'est une realisation, enregistrer la date
        if ($transition === 'realiser') {
            $operation->setDateRealisation(new \DateTimeImmutable());
        }

        $this->operationWorkflow->apply($operation, $transition);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Recupere les transitions disponibles pour une operation.
     *
     * @return array<string, string> [nom_transition => label]
     */
    public function getTransitionsDisponibles(Operation $operation): array
    {
        $transitions = [];
        $labels = [
            'planifier' => 'Planifier',
            'demarrer' => 'Demarrer',
            'realiser' => 'Realiser',
            'reporter' => 'Reporter',
            'remedier' => 'A remedier',
            'replanifier' => 'Replanifier',
        ];

        foreach ($this->operationWorkflow->getEnabledTransitions($operation) as $t) {
            $name = $t->getName();
            $transitions[$name] = $labels[$name] ?? ucfirst($name);
        }

        return $transitions;
    }

    /**
     * Assigne un technicien a une operation.
     * RG-018 : 1 operation = 1 technicien assigne maximum
     */
    public function assignerTechnicien(Operation $operation, ?Utilisateur $technicien): void
    {
        // Verifier que c'est bien un technicien si non null
        if ($technicien !== null && !$technicien->isTechnicien()) {
            throw new \InvalidArgumentException('Seul un technicien peut etre assigne a une operation.');
        }

        $operation->setTechnicienAssigne($technicien);
        $this->entityManager->flush();
    }

    /**
     * Calcule les statistiques des operations d'une campagne par statut.
     *
     * @return array<string, array{count: int, couleur: string, label: string, icone: string}>
     */
    public function getStatistiquesParStatut(Campagne $campagne): array
    {
        $counts = $this->operationRepository->countByStatutForCampagne($campagne->getId());

        $icones = [
            Operation::STATUT_A_PLANIFIER => 'clock',
            Operation::STATUT_PLANIFIE => 'calendar',
            Operation::STATUT_EN_COURS => 'play-circle',
            Operation::STATUT_REALISE => 'check-circle',
            Operation::STATUT_REPORTE => 'pause-circle',
            Operation::STATUT_A_REMEDIER => 'alert-triangle',
        ];

        $stats = [];
        foreach (Operation::STATUTS as $statut => $label) {
            $stats[$statut] = [
                'count' => $counts[$statut] ?? 0,
                'couleur' => Operation::STATUTS_COULEURS[$statut] ?? 'muted',
                'label' => $label,
                'icone' => $icones[$statut] ?? 'circle',
            ];
        }

        return $stats;
    }

    /**
     * Calcule les statistiques par segment pour une campagne.
     *
     * @return array<int, array{
     *     segment: Segment,
     *     total: int,
     *     par_statut: array<string, int>,
     *     progression: float,
     *     en_retard: bool
     * }>
     */
    public function getStatistiquesParSegment(Campagne $campagne): array
    {
        $segments = $this->segmentRepository->findByCampagne($campagne->getId());
        $stats = [];

        foreach ($segments as $segment) {
            $operations = $segment->getOperations();
            $total = $operations->count();

            $parStatut = [];
            foreach (Operation::STATUTS as $statut => $label) {
                $parStatut[$statut] = 0;
            }

            $realises = 0;
            $enRetard = 0;

            foreach ($operations as $operation) {
                $parStatut[$operation->getStatut()]++;
                if ($operation->getStatut() === Operation::STATUT_REALISE) {
                    $realises++;
                }
                // Considere "en retard" si reporte ou a remedier
                if (in_array($operation->getStatut(), [Operation::STATUT_REPORTE, Operation::STATUT_A_REMEDIER], true)) {
                    $enRetard++;
                }
            }

            $progression = $total > 0 ? round(($realises / $total) * 100, 1) : 0;

            // En retard si plus de 15% des operations sont reportees ou a remedier
            $tauxRetard = $total > 0 ? ($enRetard / $total) * 100 : 0;

            $stats[$segment->getId()] = [
                'segment' => $segment,
                'total' => $total,
                'par_statut' => $parStatut,
                'progression' => $progression,
                'en_retard' => $tauxRetard > 15,
            ];
        }

        return $stats;
    }

    /**
     * Recupere les statistiques d'un segment specifique.
     */
    public function getStatistiquesSegment(Segment $segment): array
    {
        $operations = $segment->getOperations();
        $total = $operations->count();

        $parStatut = [];
        foreach (Operation::STATUTS as $statut => $label) {
            $parStatut[$statut] = 0;
        }

        $realises = 0;
        foreach ($operations as $operation) {
            $parStatut[$operation->getStatut()]++;
            if ($operation->getStatut() === Operation::STATUT_REALISE) {
                $realises++;
            }
        }

        $progression = $total > 0 ? round(($realises / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'par_statut' => $parStatut,
            'progression' => $progression,
            'realises' => $realises,
        ];
    }

    /**
     * Cree un nouveau segment pour une campagne.
     */
    public function creerSegment(Campagne $campagne, string $nom, string $couleur = 'primary'): Segment
    {
        // Trouver l'ordre max actuel
        $segments = $campagne->getSegments();
        $maxOrdre = 0;
        foreach ($segments as $s) {
            if ($s->getOrdre() > $maxOrdre) {
                $maxOrdre = $s->getOrdre();
            }
        }

        $segment = new Segment();
        $segment->setNom($nom);
        $segment->setCouleur($couleur);
        $segment->setCampagne($campagne);
        $segment->setOrdre($maxOrdre + 1);

        $this->entityManager->persist($segment);
        $this->entityManager->flush();

        return $segment;
    }

    /**
     * Met a jour un segment.
     */
    public function modifierSegment(Segment $segment, string $nom, string $couleur): Segment
    {
        $segment->setNom($nom);
        $segment->setCouleur($couleur);
        $this->entityManager->flush();

        return $segment;
    }

    /**
     * Supprime un segment (deplace les operations vers "sans segment").
     */
    public function supprimerSegment(Segment $segment): void
    {
        // Retirer le segment de toutes les operations
        foreach ($segment->getOperations() as $operation) {
            $operation->setSegment(null);
        }

        $this->entityManager->remove($segment);
        $this->entityManager->flush();
    }

    /**
     * Assigne une operation a un segment.
     */
    public function assignerSegment(Operation $operation, ?Segment $segment): void
    {
        $operation->setSegment($segment);
        $this->entityManager->flush();
    }

    /**
     * Planifie une operation avec une date.
     */
    public function planifierOperation(Operation $operation, \DateTimeImmutable $date): bool
    {
        if (!$this->operationWorkflow->can($operation, 'planifier')) {
            return false;
        }

        $operation->setDatePlanifiee($date);
        $this->operationWorkflow->apply($operation, 'planifier');
        $this->entityManager->flush();

        return true;
    }
}
