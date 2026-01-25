<?php

namespace App\Service\Sms;

use Psr\Log\LoggerInterface;

/**
 * Provider SMS via OVH (recommande pour le secteur public francais).
 *
 * Prerequis : composer require ovh/ovh
 *
 * Configuration requise dans .env :
 * - OVH_APPLICATION_KEY
 * - OVH_APPLICATION_SECRET
 * - OVH_CONSUMER_KEY
 * - OVH_SMS_SERVICE (ex: sms-xxxxx)
 * - OVH_SMS_SENDER (optionnel, defaut: OpsTracker)
 */
class OvhSmsProvider implements SmsProviderInterface
{
    private ?\Ovh\Api $api = null;

    public function __construct(
        private string $applicationKey,
        private string $applicationSecret,
        private string $consumerKey,
        private string $serviceName,
        private string $sender,
        private LoggerInterface $logger,
    ) {
    }

    public function send(string $to, string $message): bool
    {
        try {
            $api = $this->getApi();

            $result = $api->post("/sms/{$this->serviceName}/jobs", [
                'receivers' => [$to],
                'message' => $message,
                'sender' => $this->sender,
                'noStopClause' => true, // Pas de mention STOP (notif interne)
            ]);

            // FINDING-002 : Masquer le telephone dans les logs (RGPD Art. 32)
            $this->logger->info('[SMS OVH] Message envoye', [
                'to' => $this->maskPhone($to),
                'jobId' => $result['ids'][0] ?? null,
                'credits' => $result['totalCreditsRemoved'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            // FINDING-002 : Masquer le telephone dans les logs d'erreur aussi
            $this->logger->error('[SMS OVH] Erreur envoi', [
                'to' => $this->maskPhone($to),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'ovh';
    }

    /**
     * Masque le numero de telephone pour les logs (RGPD Art. 32).
     *
     * FINDING-002 : Les donnees personnelles ne doivent pas apparaitre en clair dans les logs.
     * Exemple : +33612345678 → +336****5678
     */
    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length < 8) {
            return '****';
        }

        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }

    /**
     * Retourne l'instance de l'API OVH (lazy loading).
     */
    private function getApi(): \Ovh\Api
    {
        if ($this->api === null) {
            if (!class_exists(\Ovh\Api::class)) {
                throw new \RuntimeException(
                    'Le package OVH n\'est pas installe. Executez: composer require ovh/ovh'
                );
            }

            $this->api = new \Ovh\Api(
                $this->applicationKey,
                $this->applicationSecret,
                'ovh-eu',
                $this->consumerKey
            );
        }

        return $this->api;
    }
}
