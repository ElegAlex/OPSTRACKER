<?php

namespace App\Service;

use App\Entity\Campagne;
use App\Repository\AgentRepository;
use App\Repository\CampagneAgentAutoriseRepository;

/**
 * Service pour recuperer les personnes autorisees a reserver selon le mode de la campagne.
 *
 * Modes supportes :
 * - libre : pas de liste, saisie texte
 * - import : liste CSV specifique a la campagne (CampagneAgentAutorise)
 * - annuaire : table Agent filtree selon criteres
 */
class PersonnesAutoriseesService
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly CampagneAgentAutoriseRepository $campagneAgentAutoriseRepository,
    ) {
    }

    /**
     * Recupere la liste des personnes autorisees selon le mode de la campagne.
     *
     * @return array<int, array{id: string, label: string, service: ?string, site: ?string, email: ?string}>
     */
    public function getPersonnesAutorisees(Campagne $campagne): array
    {
        $mode = $campagne->getReservationMode();

        return match ($mode) {
            Campagne::RESERVATION_MODE_LIBRE => [],
            Campagne::RESERVATION_MODE_IMPORT => $this->getFromImport($campagne),
            Campagne::RESERVATION_MODE_ANNUAIRE => $this->getFromAnnuaire($campagne),
            default => [],
        };
    }

    /**
     * Mode import : recupere les agents depuis CampagneAgentAutorise.
     *
     * @return array<int, array{id: string, label: string, service: ?string, site: ?string, email: ?string}>
     */
    private function getFromImport(Campagne $campagne): array
    {
        $agents = $this->campagneAgentAutoriseRepository->findByCampagne($campagne);

        return array_map(fn ($a) => [
            'id' => (string) $a->getIdentifiant(),
            'label' => (string) $a->getNomPrenom(),
            'service' => $a->getService(),
            'site' => $a->getSite(),
            'email' => $a->getEmail(),
        ], $agents);
    }

    /**
     * Mode annuaire : recupere les agents depuis la table Agent avec filtres.
     *
     * @return array<int, array{id: string, label: string, service: ?string, site: ?string, email: ?string}>
     */
    private function getFromAnnuaire(Campagne $campagne): array
    {
        $filtres = $campagne->getReservationFiltresAnnuaire() ?? [];

        $qb = $this->agentRepository->createQueryBuilder('a')
            ->andWhere('a.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('a.prenom', 'ASC');

        // Appliquer les filtres si definis
        if (!empty($filtres['services'])) {
            $qb->andWhere('a.service IN (:services)')
               ->setParameter('services', $filtres['services']);
        }

        if (!empty($filtres['sites'])) {
            $qb->andWhere('a.site IN (:sites)')
               ->setParameter('sites', $filtres['sites']);
        }

        if (!empty($filtres['roles'])) {
            $qb->andWhere('a.role IN (:roles)')
               ->setParameter('roles', $filtres['roles']);
        }

        if (!empty($filtres['typesContrat'])) {
            $qb->andWhere('a.typeContrat IN (:types)')
               ->setParameter('types', $filtres['typesContrat']);
        }

        $agents = $qb->getQuery()->getResult();

        return array_map(fn ($a) => [
            'id' => $a->getMatricule() ?? $a->getEmail() ?? (string) $a->getId(),
            'label' => trim($a->getNom() . ' ' . $a->getPrenom()),
            'service' => $a->getService(),
            'site' => $a->getSite(),
            'email' => $a->getEmail(),
        ], $agents);
    }

    /**
     * Compte le nombre de personnes autorisees selon le mode.
     */
    public function countPersonnesAutorisees(Campagne $campagne): int
    {
        $mode = $campagne->getReservationMode();

        return match ($mode) {
            Campagne::RESERVATION_MODE_LIBRE => 0,
            Campagne::RESERVATION_MODE_IMPORT => $this->campagneAgentAutoriseRepository->countByCampagne($campagne),
            Campagne::RESERVATION_MODE_ANNUAIRE => $this->countFromAnnuaire($campagne),
            default => 0,
        };
    }

    /**
     * Compte les agents correspondant aux filtres annuaire.
     */
    private function countFromAnnuaire(Campagne $campagne): int
    {
        $filtres = $campagne->getReservationFiltresAnnuaire() ?? [];

        $qb = $this->agentRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.actif = :actif')
            ->setParameter('actif', true);

        if (!empty($filtres['services'])) {
            $qb->andWhere('a.service IN (:services)')
               ->setParameter('services', $filtres['services']);
        }

        if (!empty($filtres['sites'])) {
            $qb->andWhere('a.site IN (:sites)')
               ->setParameter('sites', $filtres['sites']);
        }

        if (!empty($filtres['roles'])) {
            $qb->andWhere('a.role IN (:roles)')
               ->setParameter('roles', $filtres['roles']);
        }

        if (!empty($filtres['typesContrat'])) {
            $qb->andWhere('a.typeContrat IN (:types)')
               ->setParameter('types', $filtres['typesContrat']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Recupere les valeurs distinctes disponibles pour les filtres annuaire.
     *
     * @return array{services: string[], sites: string[], roles: string[], typesContrat: string[]}
     */
    public function getValeursDisponiblesAnnuaire(): array
    {
        return [
            'services' => $this->agentRepository->getDistinctValues('service'),
            'sites' => $this->agentRepository->getDistinctValues('site'),
            'roles' => $this->agentRepository->getDistinctValues('role'),
            'typesContrat' => $this->agentRepository->getDistinctValues('typeContrat'),
        ];
    }

    /**
     * Recupere les informations completes d'une personne par son identifiant.
     *
     * @return array{nomPrenom: ?string, service: ?string, site: ?string, email: ?string}|null
     */
    public function getPersonneInfos(Campagne $campagne, string $identifiant): ?array
    {
        $mode = $campagne->getReservationMode();

        if ($mode === Campagne::RESERVATION_MODE_LIBRE) {
            // Mode libre : on retourne juste l'identifiant comme nom
            return [
                'nomPrenom' => $identifiant,
                'service' => null,
                'site' => null,
                'email' => null,
            ];
        }

        if ($mode === Campagne::RESERVATION_MODE_IMPORT) {
            $agent = $this->campagneAgentAutoriseRepository->findOneByIdentifiant($campagne, $identifiant);
            if ($agent) {
                return [
                    'nomPrenom' => $agent->getNomPrenom(),
                    'service' => $agent->getService(),
                    'site' => $agent->getSite(),
                    'email' => $agent->getEmail(),
                ];
            }
        }

        if ($mode === Campagne::RESERVATION_MODE_ANNUAIRE) {
            // Chercher dans les personnes autorisees
            $personnes = $this->getPersonnesAutorisees($campagne);
            foreach ($personnes as $personne) {
                if ($personne['id'] === $identifiant) {
                    return [
                        'nomPrenom' => $personne['label'],
                        'service' => $personne['service'],
                        'site' => $personne['site'],
                        'email' => $personne['email'],
                    ];
                }
            }
        }

        return null;
    }
}
