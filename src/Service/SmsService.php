<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\Notification;
use App\Entity\Reservation;
use App\Service\Sms\SmsProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service d'envoi de SMS pour OpsTracker V2.
 *
 * Gere l'envoi des rappels SMS J-1, confirmations et annulations.
 * Respecte l'opt-in des agents (RGPD).
 *
 * Regles metier :
 * - SMS envoye uniquement si smsOptIn = true ET telephone valide
 * - SMS desactivable globalement via SMS_ENABLED=false
 * - Historisation dans la table Notification
 */
class SmsService
{
    // Types de notification SMS
    public const TYPE_RAPPEL_SMS = 'rappel_sms';
    public const TYPE_CONFIRMATION_SMS = 'confirmation_sms';
    public const TYPE_ANNULATION_SMS = 'annulation_sms';

    public function __construct(
        private SmsProviderInterface $provider,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private bool $smsEnabled,
    ) {
    }

    /**
     * Envoie un SMS de rappel J-1.
     */
    public function envoyerRappel(Reservation $reservation): bool
    {
        $agent = $reservation->getAgent();

        if (!$this->canSend($agent)) {
            return false;
        }

        $creneau = $reservation->getCreneau();
        $message = sprintf(
            '[OpsTracker] Rappel: RDV demain %s a %s. Lieu: %s',
            $creneau->getDate()->format('d/m'),
            $creneau->getHeureDebut()->format('H:i'),
            $creneau->getLieu() ?? 'voir email'
        );

        return $this->send($agent, $message, $reservation, self::TYPE_RAPPEL_SMS);
    }

    /**
     * Envoie un SMS de confirmation.
     */
    public function envoyerConfirmation(Reservation $reservation): bool
    {
        $agent = $reservation->getAgent();

        if (!$this->canSend($agent)) {
            return false;
        }

        $creneau = $reservation->getCreneau();
        $message = sprintf(
            '[OpsTracker] Confirme: %s a %s. Details par email.',
            $creneau->getDate()->format('d/m/Y'),
            $creneau->getHeureDebut()->format('H:i')
        );

        return $this->send($agent, $message, $reservation, self::TYPE_CONFIRMATION_SMS);
    }

    /**
     * Envoie un SMS d'annulation.
     */
    public function envoyerAnnulation(Reservation $reservation): bool
    {
        $agent = $reservation->getAgent();

        if (!$this->canSend($agent)) {
            return false;
        }

        $message = '[OpsTracker] Votre RDV a ete annule. Consultez vos emails pour vous repositionner.';

        return $this->send($agent, $message, $reservation, self::TYPE_ANNULATION_SMS);
    }

    /**
     * Verifie si l'agent peut recevoir des SMS.
     */
    private function canSend(Agent $agent): bool
    {
        if (!$this->smsEnabled) {
            $this->logger->debug('[SMS] Desactive globalement');

            return false;
        }

        if (!$agent->canReceiveSms()) {
            $this->logger->debug('[SMS] Agent ne peut pas recevoir de SMS', [
                'agent' => $agent->getId(),
                'optIn' => $agent->isSmsOptIn(),
                'telephone' => !empty($agent->getTelephone()),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Envoie un SMS et enregistre la notification.
     */
    private function send(Agent $agent, string $message, ?Reservation $reservation, string $type): bool
    {
        // Creer la notification en base (avant envoi)
        $notification = new Notification();
        $notification->setAgent($agent);
        $notification->setReservation($reservation);
        $notification->setType($type);
        $notification->setSujet('SMS');
        $notification->setContenu($message);

        $this->entityManager->persist($notification);

        // Envoyer via le provider
        $success = $this->provider->send($agent->getTelephone(), $message);

        if ($success) {
            $notification->markAsSent();
            $this->logger->info('[SMS] Envoye avec succes', [
                'agent' => $agent->getId(),
                'type' => $type,
                'provider' => $this->provider->getProviderName(),
            ]);
        } else {
            $notification->markAsFailed('Erreur envoi provider ' . $this->provider->getProviderName());
            $this->logger->warning('[SMS] Echec envoi', [
                'agent' => $agent->getId(),
                'type' => $type,
                'provider' => $this->provider->getProviderName(),
            ]);
        }

        $this->entityManager->flush();

        return $success;
    }

    /**
     * Indique si le service SMS est active.
     */
    public function isEnabled(): bool
    {
        return $this->smsEnabled;
    }

    /**
     * Retourne le nom du provider utilise.
     */
    public function getProviderName(): string
    {
        return $this->provider->getProviderName();
    }
}
