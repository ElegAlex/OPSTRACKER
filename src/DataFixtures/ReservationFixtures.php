<?php

namespace App\DataFixtures;

use App\Entity\Agent;
use App\Entity\Campagne;
use App\Entity\Creneau;
use App\Entity\Reservation;
use App\Repository\CampagneRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

/**
 * Fixtures V2 - Module Reservation (Sprint 16 - T-1610)
 *
 * Donnees generees :
 * - 5 managers
 * - 50 agents (10 par manager)
 * - 60 creneaux sur 5 jours (pour la campagne en cours)
 * - 30 reservations de demonstration
 */
class ReservationFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['v2', 'reservation'];
    }

    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $manager): void
    {
        // Seed pour reproductibilite
        $this->faker->seed(2026);

        // 1. Creer les managers et agents
        $agents = $this->createAgents($manager);

        // 2. Recuperer une campagne active (en cours ou a venir)
        $campagneRepository = $manager->getRepository(Campagne::class);
        $campagneActive = $campagneRepository->findOneBy(['statut' => Campagne::STATUT_EN_COURS]);

        if ($campagneActive === null) {
            // Fallback : prendre la campagne a venir
            $campagneActive = $campagneRepository->findOneBy(['statut' => Campagne::STATUT_A_VENIR]);
        }

        if ($campagneActive === null) {
            // Fallback : prendre n'importe quelle campagne active
            $campagneActive = $campagneRepository->findOneBy(['statut' => Campagne::STATUT_PREPARATION]);
        }

        if ($campagneActive === null) {
            return;
        }

        // 3. Creer les creneaux
        $creneaux = $this->createCreneaux($manager, $campagneActive);

        // 4. Creer les reservations
        $this->createReservations($manager, $agents, $creneaux, $campagneActive);

        $manager->flush();
    }

    /**
     * Cree les managers et agents
     *
     * @return Agent[]
     */
    private function createAgents(ObjectManager $manager): array
    {
        $agents = [];
        $managers = [];

        // Services disponibles
        $services = [
            'Service A',
            'Service B',
            'Accueil',
            'Gestion des Droits',
            'Comptabilite',
        ];

        // Sites disponibles
        $sites = [
            'Site Central',
            'Site Nord',
            'Site Est',
        ];

        // Creer 5 managers (un par service)
        foreach ($services as $index => $service) {
            $mgr = new Agent();
            $mgr->setMatricule(sprintf('MGR%03d', $index + 1));
            $mgr->setEmail(sprintf('manager.%s@demo.opstracker.local', $this->slugify($service)));
            $mgr->setNom($this->faker->lastName());
            $mgr->setPrenom($this->faker->firstName());
            $mgr->setService($service);
            $mgr->setSite($sites[array_rand($sites)]);
            $mgr->setActif(true);
            $mgr->generateBookingToken();
            // SMS opt-in pour 1/3 des managers
            $mgr->setTelephone('+33' . $this->faker->numerify('6########'));
            $mgr->setSmsOptIn($index % 3 === 0);

            $manager->persist($mgr);
            $managers[$service] = $mgr;
            $agents[] = $mgr;
        }

        // Creer 50 agents (10 par service)
        $agentNum = 1;
        foreach ($services as $service) {
            for ($i = 0; $i < 10; $i++) {
                $agent = new Agent();
                $agent->setMatricule(sprintf('AGT%04d', $agentNum));
                $agent->setEmail(sprintf('agent.%04d@demo.opstracker.local', $agentNum));
                $agent->setNom($this->faker->lastName());
                $agent->setPrenom($this->faker->firstName());
                $agent->setService($service);
                $agent->setSite($sites[array_rand($sites)]);
                $agent->setManager($managers[$service]);
                $agent->setActif(true);
                $agent->generateBookingToken();
                // SMS opt-in pour 1/3 des agents
                $agent->setTelephone('+33' . $this->faker->numerify('6########'));
                $agent->setSmsOptIn($agentNum % 3 === 0);

                $manager->persist($agent);
                $agents[] = $agent;
                $agentNum++;
            }
        }

        return $agents;
    }

    /**
     * Cree les creneaux pour une campagne
     *
     * @return Creneau[]
     */
    private function createCreneaux(ObjectManager $manager, Campagne $campagne): array
    {
        $creneaux = [];
        $segments = $campagne->getSegments()->toArray();

        // 60 creneaux sur 5 jours (12 par jour)
        // 9h-12h : 6 creneaux de 30 min
        // 14h-17h : 6 creneaux de 30 min
        $baseDate = new \DateTime('2026-02-03'); // Debut semaine prochaine

        for ($jour = 0; $jour < 5; $jour++) {
            $currentDate = clone $baseDate;
            $currentDate->modify("+{$jour} days");

            // Matin : 9h-12h
            $heures = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'];
            foreach ($heures as $heure) {
                $creneau = $this->createCreneau(
                    $manager,
                    $campagne,
                    clone $currentDate,
                    $heure,
                    30, // duree minutes
                    2,  // capacite
                    $segments[array_rand($segments)] ?? null
                );
                $creneaux[] = $creneau;
            }

            // Apres-midi : 14h-17h
            $heures = ['14:00', '14:30', '15:00', '15:30', '16:00', '16:30'];
            foreach ($heures as $heure) {
                $creneau = $this->createCreneau(
                    $manager,
                    $campagne,
                    clone $currentDate,
                    $heure,
                    30, // duree minutes
                    2,  // capacite
                    $segments[array_rand($segments)] ?? null
                );
                $creneaux[] = $creneau;
            }
        }

        return $creneaux;
    }

    /**
     * Cree un creneau
     */
    private function createCreneau(
        ObjectManager $manager,
        Campagne $campagne,
        \DateTime $date,
        string $heureDebut,
        int $dureeMinutes,
        int $capacite,
        $segment = null
    ): Creneau {
        $debut = \DateTime::createFromFormat('H:i', $heureDebut);
        $fin = clone $debut;
        $fin->modify("+{$dureeMinutes} minutes");

        $creneau = new Creneau();
        $creneau->setCampagne($campagne);
        $creneau->setDate($date);
        $creneau->setHeureDebut($debut);
        $creneau->setHeureFin($fin);
        $creneau->setCapacite($capacite);
        $creneau->setLieu('Salle ' . $this->faker->numberBetween(1, 10));
        $creneau->setSegment($segment);
        $creneau->setVerrouille(false);

        $manager->persist($creneau);

        return $creneau;
    }

    /**
     * Cree les reservations de demonstration
     *
     * @param Agent[] $agents
     * @param Creneau[] $creneaux
     */
    private function createReservations(
        ObjectManager $manager,
        array $agents,
        array $creneaux,
        Campagne $campagne
    ): void {
        // Filtrer les agents (exclure les managers)
        $agentsNonManagers = array_filter($agents, fn (Agent $a) => $a->getManager() !== null);

        // Creer 30 reservations
        $agentsReserves = [];
        $creneauxUtilises = [];
        $reservationCount = 0;

        foreach ($agentsNonManagers as $agent) {
            if ($reservationCount >= 30) {
                break;
            }

            // Trouver un creneau disponible
            foreach ($creneaux as $creneau) {
                $creneauId = $creneau->getId() ?? spl_object_id($creneau);

                // Compter les reservations de ce creneau
                $reservationsCount = $creneauxUtilises[$creneauId] ?? 0;
                if ($reservationsCount >= $creneau->getCapacite()) {
                    continue;
                }

                // RG-121 : Verifier unicite agent/campagne
                if (in_array($agent->getMatricule(), $agentsReserves, true)) {
                    break;
                }

                // Creer la reservation
                $reservation = new Reservation();
                $reservation->setAgent($agent);
                $reservation->setCreneau($creneau);
                $reservation->setCampagne($campagne);

                // 70% par agent, 25% par manager, 5% par coordinateur
                $rand = $this->faker->numberBetween(1, 100);
                if ($rand <= 70) {
                    $reservation->setTypePositionnement(Reservation::TYPE_AGENT);
                } elseif ($rand <= 95) {
                    $reservation->setTypePositionnement(Reservation::TYPE_MANAGER);
                } else {
                    $reservation->setTypePositionnement(Reservation::TYPE_COORDINATEUR);
                }

                $reservation->setStatut(Reservation::STATUT_CONFIRMEE);

                $manager->persist($reservation);

                $agentsReserves[] = $agent->getMatricule();
                $creneauxUtilises[$creneauId] = $reservationsCount + 1;
                $reservationCount++;

                break;
            }
        }
    }

    /**
     * Convertit une chaine en slug
     */
    private function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9]/', '.', $text);
        $text = preg_replace('/\.+/', '.', $text);
        $text = strtolower(trim($text, '.'));

        return $text;
    }
}
