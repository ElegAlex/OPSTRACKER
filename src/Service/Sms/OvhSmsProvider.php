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

            $this->logger->info('[SMS OVH] Message envoye', [
                'to' => $to,
                'jobId' => $result['ids'][0] ?? null,
                'credits' => $result['totalCreditsRemoved'] ?? null,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[SMS OVH] Erreur envoi', [
                'to' => $to,
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
