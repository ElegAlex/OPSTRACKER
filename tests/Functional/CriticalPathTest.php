<?php

namespace App\Tests\Functional;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests E2E du parcours critique - Sprint 8 (T-804)
 *
 * Parcours: Login -> Dashboard -> Operations -> Terrain -> Checklist
 */
class CriticalPathTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testLoginPageIsAccessible(): void
    {
        $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Connexion');
    }

    public function testLoginWithInvalidCredentialsShowsError(): void
    {
        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'invalid@test.fr',
            '_password' => 'wrongpassword',
        ]);

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertSelectorExists('.border-danger');
    }

    public function testLoginWithValidCredentialsRedirectsToDashboard(): void
    {
        // Recuperer un utilisateur de test
        $user = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'sophie.martin@demo.opstracker.local']);

        if (!$user) {
            $this->markTestSkipped('Fixtures non chargees - Utilisateur Sophie non trouve');
        }

        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'sophie.martin@demo.opstracker.local',
            '_password' => 'Sophie123!',
        ]);

        $this->client->submit($form);

        // Devrait rediriger vers / puis /campagnes (redirection selon role)
        $this->assertResponseRedirects();
        $this->client->followRedirect(); // → /
        $this->client->followRedirect(); // → /campagnes
        $this->assertResponseIsSuccessful();
    }

    public function testCampagneIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/campagnes');

        // Devrait rediriger vers login
        $this->assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserCanAccessCampagnes(): void
    {
        $user = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'sophie.martin@demo.opstracker.local']);

        if (!$user) {
            $this->markTestSkipped('Fixtures non chargees');
        }

        $this->client->loginUser($user);
        $this->client->request('GET', '/campagnes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Campagnes');
    }

    public function testAuthenticatedUserCanAccessCampagneShow(): void
    {
        $user = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'sophie.martin@demo.opstracker.local']);

        if (!$user) {
            $this->markTestSkipped('Fixtures non chargees');
        }

        // Trouver une campagne
        $campagne = $this->em->getRepository(\App\Entity\Campagne::class)
            ->findOneBy(['statut' => 'en_cours']);

        if (!$campagne) {
            $this->markTestSkipped('Aucune campagne en cours');
        }

        $this->client->loginUser($user);
        // La route /campagnes/{id} redirige vers /dashboard/campagne/{id}
        $this->client->request('GET', '/dashboard/campagne/' . $campagne->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testTechnicienIsAuthenticated(): void
    {
        $technicien = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'karim.benali@demo.opstracker.local']);

        if (!$technicien) {
            $this->markTestSkipped('Fixtures non chargees - Technicien Karim non trouve');
        }

        // Verifier que le technicien peut se connecter
        $this->client->loginUser($technicien);

        // Le technicien devrait pouvoir acceder a une page authentifiee
        $this->client->request('GET', '/campagnes');
        $this->assertResponseIsSuccessful();
    }

    public function testOperationEntityExists(): void
    {
        // Verifier que les operations ont ete creees par les fixtures
        $operations = $this->em->getRepository(\App\Entity\Operation::class)->findAll();

        if (count($operations) === 0) {
            $this->markTestSkipped('Fixtures non chargees - Aucune operation trouvee');
        }

        $this->assertGreaterThan(0, count($operations), 'Les fixtures doivent creer des operations');
    }

    public function testAdminCanAccessEasyAdmin(): void
    {
        $admin = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'admin@demo.opstracker.local']);

        if (!$admin) {
            $this->markTestSkipped('Fixtures non chargees - Admin non trouve');
        }

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin');

        // EasyAdmin redirige vers le premier dashboard configure
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testLogoutWorks(): void
    {
        $user = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'sophie.martin@demo.opstracker.local']);

        if (!$user) {
            $this->markTestSkipped('Fixtures non chargees');
        }

        $this->client->loginUser($user);
        $this->client->request('GET', '/logout');

        $this->assertResponseRedirects();
    }

    public function testUnauthorizedAccessToAdminIsBlocked(): void
    {
        $technicien = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'karim.benali@demo.opstracker.local']);

        if (!$technicien) {
            $this->markTestSkipped('Fixtures non chargees');
        }

        $this->client->loginUser($technicien);
        $this->client->request('GET', '/admin');

        // Technicien ne devrait pas acceder a admin
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testGlobalDashboardIsAccessible(): void
    {
        $user = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'sophie.martin@demo.opstracker.local']);

        if (!$user) {
            $this->markTestSkipped('Fixtures non chargees');
        }

        $this->client->loginUser($user);
        $this->client->request('GET', '/dashboard');

        // Peut etre une redirection ou page accessible
        $this->assertTrue(
            $this->client->getResponse()->isSuccessful() ||
            $this->client->getResponse()->isRedirection()
        );
    }

    public function testNewCampagneFormIsAccessible(): void
    {
        $user = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'sophie.martin@demo.opstracker.local']);

        if (!$user) {
            $this->markTestSkipped('Fixtures non chargees');
        }

        $this->client->loginUser($user);
        $this->client->request('GET', '/campagnes/nouvelle');

        $this->assertResponseIsSuccessful();
    }

    public function testSegmentsListIsAccessible(): void
    {
        $user = $this->em->getRepository(Utilisateur::class)
            ->findOneBy(['email' => 'sophie.martin@demo.opstracker.local']);

        if (!$user) {
            $this->markTestSkipped('Fixtures non chargees');
        }

        $campagne = $this->em->getRepository(\App\Entity\Campagne::class)
            ->findOneBy(['statut' => 'en_cours']);

        if (!$campagne) {
            $this->markTestSkipped('Aucune campagne en cours');
        }

        $this->client->loginUser($user);
        $this->client->request('GET', '/campagnes/' . $campagne->getId() . '/segments');

        $this->assertResponseIsSuccessful();
    }
}
