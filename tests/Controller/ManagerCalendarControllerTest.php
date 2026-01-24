<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Campagne;
use App\Entity\Utilisateur;
use App\Repository\CampagneRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests du ManagerCalendarController (T-2305).
 *
 * Sprint V2.1b - US-1014 : Vue calendrier planning
 *
 * Scenarios testes :
 * 1. Page calendrier accessible pour un manager
 * 2. API events.json retourne du JSON valide
 * 3. Les evenements contiennent les champs requis
 * 4. Manager non autorise ne peut pas acceder
 * 5. Calendrier affiche les elements attendus
 */
class ManagerCalendarControllerTest extends WebTestCase
{
    private function getGestionnaireFromClient(KernelBrowser $client): ?Utilisateur
    {
        $container = $client->getContainer();
        /** @var UtilisateurRepository $repo */
        $repo = $container->get(UtilisateurRepository::class);

        // Recuperer tous les utilisateurs et filtrer en PHP
        $utilisateurs = $repo->findAll();
        foreach ($utilisateurs as $user) {
            if (in_array('ROLE_GESTIONNAIRE', $user->getRoles(), true)) {
                return $user;
            }
        }

        return null;
    }

    private function getCampagneActiveFromClient(KernelBrowser $client): ?Campagne
    {
        $container = $client->getContainer();
        /** @var CampagneRepository $repo */
        $repo = $container->get(CampagneRepository::class);

        return $repo->findOneBy(['statut' => 'en_cours']);
    }

    /**
     * Test 1 : La page calendrier est accessible pour un gestionnaire.
     */
    public function testCalendarPageLoadsForManager(): void
    {
        $client = static::createClient();

        $manager = $this->getGestionnaireFromClient($client);
        if (!$manager) {
            $this->markTestSkipped('Aucun gestionnaire disponible.');
        }

        $client->loginUser($manager);

        $campagne = $this->getCampagneActiveFromClient($client);
        if (!$campagne) {
            $this->markTestSkipped('Aucune campagne active.');
        }

        $client->request('GET', '/manager/campagne/' . $campagne->getId() . '/calendar');

        $response = $client->getResponse();

        // La page doit etre accessible ou rediriger (si pas d'agent associe)
        $this->assertTrue(
            $response->isSuccessful() || $response->isRedirect(),
            'La page calendrier doit etre accessible pour un gestionnaire.'
        );

        // Si la page est accessible, verifier les elements cles
        if ($response->isSuccessful()) {
            $content = $response->getContent();

            // Verifier la presence de FullCalendar
            $this->assertStringContainsString(
                'fullcalendar',
                strtolower($content),
                'La page doit inclure FullCalendar.'
            );

            // Verifier la presence du calendrier
            $this->assertStringContainsString(
                'id="calendar"',
                $content,
                'La page doit contenir l\'element calendrier.'
            );

            // Verifier la legende
            $this->assertStringContainsString(
                'Disponible',
                $content,
                'La legende doit etre presente.'
            );
        }
    }

    /**
     * Test 2 : L'API events.json retourne du JSON valide.
     */
    public function testCalendarEventsReturnsJson(): void
    {
        $client = static::createClient();

        $manager = $this->getGestionnaireFromClient($client);
        if (!$manager) {
            $this->markTestSkipped('Aucun gestionnaire disponible.');
        }

        $client->loginUser($manager);

        $campagne = $this->getCampagneActiveFromClient($client);
        if (!$campagne) {
            $this->markTestSkipped('Aucune campagne active.');
        }

        $client->request('GET', '/manager/campagne/' . $campagne->getId() . '/calendar/events.json', [
            'start' => date('Y-m-01'),
            'end' => date('Y-m-t'),
        ]);

        $response = $client->getResponse();

        // L'API peut retourner 403 si le manager n'a pas d'agent associe
        if ($response->getStatusCode() === 403) {
            $this->markTestSkipped('Manager non associe a un agent.');
        }

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data, 'La reponse doit etre un tableau JSON.');
    }

    /**
     * Test 3 : Les evenements contiennent les champs requis par FullCalendar.
     */
    public function testCalendarEventsHaveRequiredFields(): void
    {
        $client = static::createClient();

        $manager = $this->getGestionnaireFromClient($client);
        if (!$manager) {
            $this->markTestSkipped('Aucun gestionnaire disponible.');
        }

        $client->loginUser($manager);

        $campagne = $this->getCampagneActiveFromClient($client);
        if (!$campagne) {
            $this->markTestSkipped('Aucune campagne active.');
        }

        // Utiliser une plage large pour avoir des creneaux
        $start = date('Y-01-01');
        $end = date('Y-12-31');

        $client->request('GET', '/manager/campagne/' . $campagne->getId() . '/calendar/events.json', [
            'start' => $start,
            'end' => $end,
        ]);

        $response = $client->getResponse();

        if ($response->getStatusCode() === 403) {
            $this->markTestSkipped('Manager non associe a un agent.');
        }

        $data = json_decode($response->getContent(), true);

        if (count($data) > 0) {
            $event = $data[0];

            // Champs obligatoires FullCalendar
            $this->assertArrayHasKey('id', $event, 'L\'evenement doit avoir un id.');
            $this->assertArrayHasKey('title', $event, 'L\'evenement doit avoir un title.');
            $this->assertArrayHasKey('start', $event, 'L\'evenement doit avoir un start.');
            $this->assertArrayHasKey('end', $event, 'L\'evenement doit avoir un end.');

            // Champs de couleur
            $this->assertArrayHasKey('backgroundColor', $event, 'L\'evenement doit avoir une backgroundColor.');
            $this->assertArrayHasKey('borderColor', $event, 'L\'evenement doit avoir une borderColor.');
            $this->assertArrayHasKey('textColor', $event, 'L\'evenement doit avoir une textColor.');

            // Proprietes etendues
            $this->assertArrayHasKey('extendedProps', $event, 'L\'evenement doit avoir des extendedProps.');

            $props = $event['extendedProps'];
            $this->assertArrayHasKey('creneauId', $props, 'Les props doivent avoir creneauId.');
            $this->assertArrayHasKey('capacite', $props, 'Les props doivent avoir capacite.');
            $this->assertArrayHasKey('placesRestantes', $props, 'Les props doivent avoir placesRestantes.');
            $this->assertArrayHasKey('type', $props, 'Les props doivent avoir type.');
            $this->assertArrayHasKey('mesAgents', $props, 'Les props doivent avoir mesAgents.');

            // Verifier le format de la date
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/',
                $event['start'],
                'Le format de date start doit etre ISO 8601.'
            );
        }
    }

    /**
     * Test 4 : Un utilisateur non autorise est bloque.
     */
    public function testCalendarBlocksUnauthorizedUser(): void
    {
        $client = static::createClient();

        $campagne = $this->getCampagneActiveFromClient($client);
        if (!$campagne) {
            $this->markTestSkipped('Aucune campagne active.');
        }

        // Tester sans authentification
        $client->request('GET', '/manager/campagne/' . $campagne->getId() . '/calendar');

        // Doit rediriger vers login ou retourner 401/403
        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirect() || in_array($response->getStatusCode(), [401, 403]),
            'Un utilisateur non authentifie doit etre redirige ou bloque.'
        );
    }

    /**
     * Test 5 : L'API retourne des types d'evenements corrects.
     */
    public function testCalendarEventsHaveCorrectTypes(): void
    {
        $client = static::createClient();

        $manager = $this->getGestionnaireFromClient($client);
        if (!$manager) {
            $this->markTestSkipped('Aucun gestionnaire disponible.');
        }

        $client->loginUser($manager);

        $campagne = $this->getCampagneActiveFromClient($client);
        if (!$campagne) {
            $this->markTestSkipped('Aucune campagne active.');
        }

        $start = date('Y-01-01');
        $end = date('Y-12-31');

        $client->request('GET', '/manager/campagne/' . $campagne->getId() . '/calendar/events.json', [
            'start' => $start,
            'end' => $end,
        ]);

        $response = $client->getResponse();

        if ($response->getStatusCode() === 403) {
            $this->markTestSkipped('Manager non associe a un agent.');
        }

        $data = json_decode($response->getContent(), true);

        $validTypes = ['disponible', 'reserve', 'complet'];

        foreach ($data as $event) {
            $type = $event['extendedProps']['type'] ?? null;
            $this->assertContains(
                $type,
                $validTypes,
                "Le type '$type' doit etre parmi : " . implode(', ', $validTypes)
            );
        }
    }

    /**
     * Test 6 : La navigation entre vues fonctionne.
     */
    public function testCalendarHasNavigationButtons(): void
    {
        $client = static::createClient();

        $manager = $this->getGestionnaireFromClient($client);
        if (!$manager) {
            $this->markTestSkipped('Aucun gestionnaire disponible.');
        }

        $client->loginUser($manager);

        $campagne = $this->getCampagneActiveFromClient($client);
        if (!$campagne) {
            $this->markTestSkipped('Aucune campagne active.');
        }

        $crawler = $client->request('GET', '/manager/campagne/' . $campagne->getId() . '/calendar');

        $response = $client->getResponse();

        if ($response->isRedirect()) {
            $this->markTestSkipped('Redirection (manager sans agent associe).');
        }

        if ($response->isSuccessful()) {
            $content = $response->getContent();

            // Verifier les liens de navigation
            $this->assertStringContainsString(
                'Vue liste',
                $content,
                'Le lien vers la vue liste doit etre present.'
            );

            $this->assertStringContainsString(
                'Vue planning',
                $content,
                'Le lien vers la vue planning doit etre present.'
            );
        }
    }

}
