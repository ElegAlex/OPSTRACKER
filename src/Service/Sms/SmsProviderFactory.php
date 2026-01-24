<?php

namespace App\Service\Sms;

use Psr\Log\LoggerInterface;

/**
 * Factory pour creer le bon provider SMS selon la configuration.
 *
 * Permet de changer de provider sans modifier le code metier.
 * Configure via la variable d'environnement SMS_PROVIDER.
 */
class SmsProviderFactory
{
    public function __construct(
        private LoggerInterface $logger,
        private ?string $ovhAppKey = null,
        private ?string $ovhAppSecret = null,
        private ?string $ovhConsumerKey = null,
        private ?string $ovhServiceName = null,
        private ?string $ovhSender = null,
    ) {
    }

    /**
     * Cree le provider SMS selon le type demande.
     *
     * @param string $provider Type de provider : 'log', 'ovh', 'twilio'
     *
     * @throws \InvalidArgumentException Si le provider n'est pas supporte
     */
    public function create(string $provider): SmsProviderInterface
    {
        return match ($provider) {
            'log' => new LogSmsProvider($this->logger),
            'ovh' => $this->createOvhProvider(),
            default => throw new \InvalidArgumentException(
                sprintf('Provider SMS non supporte : %s. Providers valides : log, ovh', $provider)
            ),
        };
    }

    /**
     * Cree le provider OVH avec validation des parametres.
     */
    private function createOvhProvider(): OvhSmsProvider
    {
        // Verifier que tous les parametres OVH sont configures
        if (empty($this->ovhAppKey) || empty($this->ovhAppSecret) || empty($this->ovhConsumerKey) || empty($this->ovhServiceName)) {
            throw new \RuntimeException(
                'Configuration OVH incomplete. Verifiez les variables : OVH_APPLICATION_KEY, OVH_APPLICATION_SECRET, OVH_CONSUMER_KEY, OVH_SMS_SERVICE'
            );
        }

        return new OvhSmsProvider(
            $this->ovhAppKey,
            $this->ovhAppSecret,
            $this->ovhConsumerKey,
            $this->ovhServiceName,
            $this->ovhSender ?? 'OpsTracker',
            $this->logger,
        );
    }
}
