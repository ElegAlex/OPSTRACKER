<?php

namespace App\Service\Sms;

use Psr\Log\LoggerInterface;

/**
 * Provider SMS pour le developpement.
 *
 * Enregistre les SMS dans les logs au lieu de les envoyer.
 * Permet de tester le fonctionnement sans consommer de credits.
 */
class LogSmsProvider implements SmsProviderInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function send(string $to, string $message): bool
    {
        $this->logger->info('[SMS LOG MODE] Message simule', [
            'to' => $to,
            'message' => $message,
            'length' => mb_strlen($message),
            'provider' => $this->getProviderName(),
        ]);

        return true;
    }

    public function getProviderName(): string
    {
        return 'log';
    }
}
