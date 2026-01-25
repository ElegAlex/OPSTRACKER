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
        // FINDING-002 : Masquer le telephone et le contenu SMS dans les logs (RGPD Art. 32)
        $this->logger->info('[SMS LOG MODE] Message simule', [
            'to' => $this->maskPhone($to),
            'message_length' => mb_strlen($message), // Ne pas logger le contenu du SMS
            'provider' => $this->getProviderName(),
        ]);

        return true;
    }

    public function getProviderName(): string
    {
        return 'log';
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
}
