<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Repository\ReservationRepository;
use League\Csv\Writer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour l'export CSV des reservations.
 *
 * User Stories :
 * - US-1012 : Export CSV reservations (T-2203)
 *
 * Regles metier :
 * - Export avec separateur point-virgule (Excel FR)
 * - Encodage UTF-8 BOM
 * - Toutes les reservations confirmees
 */
#[Route('/campagnes/{campagne}/reservations')]
#[IsGranted('ROLE_GESTIONNAIRE')]
class ReservationExportController extends AbstractController
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    /**
     * T-2203 / US-1012 : Exporter les reservations d'une campagne en CSV.
     */
    #[Route('/export.csv', name: 'app_reservation_export', methods: ['GET'])]
    public function export(Campagne $campagne): Response
    {
        $reservations = $this->reservationRepository->findByCampagne($campagne);

        $response = new StreamedResponse(function () use ($reservations): void {
            // BOM UTF-8 pour Excel
            echo "\xEF\xBB\xBF";

            $csv = Writer::createFromStream(fopen('php://output', 'w'));
            $csv->setDelimiter(';');

            // En-tetes
            $csv->insertOne([
                'Matricule',
                'Nom',
                'Prenom',
                'Email',
                'Service',
                'Site',
                'Date Creneau',
                'Heure Debut',
                'Heure Fin',
                'Lieu',
                'Statut',
                'Positionne par',
                'Type Positionnement',
                'Date Reservation',
            ]);

            // Donnees
            foreach ($reservations as $reservation) {
                $agent = $reservation->getAgent();
                $creneau = $reservation->getCreneau();
                $positionneur = $reservation->getPositionnePar();

                if (null === $agent || null === $creneau) {
                    continue;
                }

                $csv->insertOne([
                    $agent->getMatricule() ?? '',
                    $agent->getNom() ?? '',
                    $agent->getPrenom() ?? '',
                    $agent->getEmail() ?? '',
                    $agent->getService() ?? '',
                    $agent->getSite() ?? '',
                    $creneau->getDate()?->format('d/m/Y') ?? '',
                    $creneau->getHeureDebut()?->format('H:i') ?? '',
                    $creneau->getHeureFin()?->format('H:i') ?? '',
                    $creneau->getLieu() ?? '',
                    $reservation->getStatutLabel(),
                    $positionneur?->getEmail() ?? 'Auto',
                    $reservation->getTypePositionnementLabel(),
                    $reservation->getCreatedAt()?->format('d/m/Y H:i') ?? '',
                ]);
            }
        });

        $filename = sprintf(
            'reservations_%s_%s.csv',
            $campagne->getId(),
            date('Y-m-d')
        );

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
