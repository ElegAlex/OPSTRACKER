<?php

namespace App\Service;

use App\Entity\Reservation;

/**
 * Service de generation de fichiers ICS pour OpsTracker V2.
 *
 * Genere des fichiers iCalendar (.ics) pour les reservations.
 * Compatible avec Outlook, Google Calendar, Apple Calendar, etc.
 *
 * Regle metier :
 * - RG-140 : Email confirmation contient ICS obligatoire
 */
class IcsGenerator
{
    private string $prodId;
    private string $domain;

    public function __construct(
        string $prodId = '-//OpsTracker//FR',
        string $domain = 'opstracker.local'
    ) {
        $this->prodId = $prodId;
        $this->domain = $domain;
    }

    /**
     * Genere le contenu ICS pour une reservation
     *
     * @throws \InvalidArgumentException Si la reservation n'est pas complete
     */
    public function generate(Reservation $reservation): string
    {
        $creneau = $reservation->getCreneau();
        $agent = $reservation->getAgent();
        $campagne = $reservation->getCampagne();

        if ($creneau === null || $agent === null || $campagne === null) {
            throw new \InvalidArgumentException('La reservation doit avoir un creneau, un agent et une campagne.');
        }

        $date = $creneau->getDate();
        $heureDebut = $creneau->getHeureDebut();
        $heureFin = $creneau->getHeureFin();

        if ($date === null || $heureDebut === null || $heureFin === null) {
            throw new \InvalidArgumentException('Le creneau doit avoir une date, une heure de debut et une heure de fin.');
        }

        // Construire les dates au format ICS
        $dateStr = $date->format('Ymd');
        $dtStart = $dateStr . 'T' . $heureDebut->format('His');
        $dtEnd = $dateStr . 'T' . $heureFin->format('His');

        // UID unique pour l'evenement
        $uid = sprintf('%d-%s@%s', $reservation->getId(), uniqid(), $this->domain);

        // Timestamp de creation
        $dtstamp = (new \DateTime())->format('Ymd\THis\Z');

        // Description de l'evenement
        $summary = sprintf('[%s] Intervention IT', $campagne->getNom());
        $description = sprintf(
            'Intervention prevue pour %s\\n' .
            'Campagne : %s\\n' .
            'Reserve le : %s',
            $agent->getNomComplet(),
            $campagne->getNom(),
            $reservation->getCreatedAt()?->format('d/m/Y H:i') ?? ''
        );

        // Lieu
        $location = $this->escapeIcsText($creneau->getLieu() ?? 'Non specifie');

        // Construire le contenu ICS
        $ics = [];
        $ics[] = 'BEGIN:VCALENDAR';
        $ics[] = 'VERSION:2.0';
        $ics[] = 'PRODID:' . $this->prodId;
        $ics[] = 'CALSCALE:GREGORIAN';
        $ics[] = 'METHOD:PUBLISH';
        $ics[] = 'BEGIN:VEVENT';
        $ics[] = 'UID:' . $uid;
        $ics[] = 'DTSTAMP:' . $dtstamp;
        $ics[] = 'DTSTART:' . $dtStart;
        $ics[] = 'DTEND:' . $dtEnd;
        $ics[] = 'SUMMARY:' . $this->escapeIcsText($summary);
        $ics[] = 'DESCRIPTION:' . $description;
        $ics[] = 'LOCATION:' . $location;

        // Ajouter un rappel J-1
        $ics[] = 'BEGIN:VALARM';
        $ics[] = 'TRIGGER:-P1D';
        $ics[] = 'ACTION:DISPLAY';
        $ics[] = 'DESCRIPTION:Rappel : intervention IT demain';
        $ics[] = 'END:VALARM';

        // Ajouter un rappel 1 heure avant
        $ics[] = 'BEGIN:VALARM';
        $ics[] = 'TRIGGER:-PT1H';
        $ics[] = 'ACTION:DISPLAY';
        $ics[] = 'DESCRIPTION:Rappel : intervention IT dans 1 heure';
        $ics[] = 'END:VALARM';

        $ics[] = 'END:VEVENT';
        $ics[] = 'END:VCALENDAR';

        return implode("\r\n", $ics);
    }

    /**
     * Genere un ICS d'annulation pour une reservation
     *
     * @throws \InvalidArgumentException Si la reservation n'est pas complete
     */
    public function generateCancellation(Reservation $reservation): string
    {
        $creneau = $reservation->getCreneau();
        $campagne = $reservation->getCampagne();

        if ($creneau === null || $campagne === null) {
            throw new \InvalidArgumentException('La reservation doit avoir un creneau et une campagne.');
        }

        $date = $creneau->getDate();
        $heureDebut = $creneau->getHeureDebut();
        $heureFin = $creneau->getHeureFin();

        if ($date === null || $heureDebut === null || $heureFin === null) {
            throw new \InvalidArgumentException('Le creneau doit avoir une date, une heure de debut et une heure de fin.');
        }

        $dateStr = $date->format('Ymd');
        $dtStart = $dateStr . 'T' . $heureDebut->format('His');
        $dtEnd = $dateStr . 'T' . $heureFin->format('His');

        $uid = sprintf('%d@%s', $reservation->getId(), $this->domain);
        $dtstamp = (new \DateTime())->format('Ymd\THis\Z');

        $summary = sprintf('[%s] Intervention IT - ANNULEE', $campagne->getNom());

        $ics = [];
        $ics[] = 'BEGIN:VCALENDAR';
        $ics[] = 'VERSION:2.0';
        $ics[] = 'PRODID:' . $this->prodId;
        $ics[] = 'METHOD:CANCEL';
        $ics[] = 'BEGIN:VEVENT';
        $ics[] = 'UID:' . $uid;
        $ics[] = 'DTSTAMP:' . $dtstamp;
        $ics[] = 'DTSTART:' . $dtStart;
        $ics[] = 'DTEND:' . $dtEnd;
        $ics[] = 'SUMMARY:' . $this->escapeIcsText($summary);
        $ics[] = 'STATUS:CANCELLED';
        $ics[] = 'END:VEVENT';
        $ics[] = 'END:VCALENDAR';

        return implode("\r\n", $ics);
    }

    /**
     * Echappe les caracteres speciaux pour le format ICS
     */
    private function escapeIcsText(string $text): string
    {
        // Echapper les caracteres speciaux
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace("\n", '\\n', $text);

        return $text;
    }
}
