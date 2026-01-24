<?php

namespace App\Service\Sms;

/**
 * Interface pour les providers SMS.
 *
 * Permet de changer facilement de provider (OVH, Twilio, Log, etc.)
 * sans modifier le code metier.
 */
interface SmsProviderInterface
{
    /**
     * Envoie un SMS a un numero de telephone.
     *
     * @param string $to      Numero de telephone au format E.164 (+33...)
     * @param string $message Contenu du SMS (max 160 caracteres recommandes)
     *
     * @return bool True si l'envoi a reussi, false sinon
     */
    public function send(string $to, string $message): bool;

    /**
     * Retourne le nom du provider pour le logging.
     */
    public function getProviderName(): string;
}
