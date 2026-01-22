<?php

namespace App\Tests\Unit\Security;

use App\Entity\Operation;
use App\Entity\Utilisateur;
use App\Security\Voter\OperationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Tests unitaires pour le OperationVoter.
 *
 * Verifie les regles d'acces aux operations :
 * - Un technicien ne peut voir/modifier que ses operations assignees
 * - Un gestionnaire peut voir/modifier toutes les operations
 * - Un admin peut tout faire
 */
class OperationVoterTest extends TestCase
{
    private OperationVoter $voter;
    private Utilisateur $technicien;
    private Utilisateur $autreTechnicien;
    private Utilisateur $gestionnaire;
    private Utilisateur $admin;
    private Operation $operationAssignee;
    private Operation $operationNonAssignee;

    protected function setUp(): void
    {
        $this->voter = new OperationVoter();

        // Creer les utilisateurs
        $this->technicien = $this->createUtilisateur(['ROLE_TECHNICIEN'], 'karim@test.local');
        $this->autreTechnicien = $this->createUtilisateur(['ROLE_TECHNICIEN'], 'autre@test.local');
        $this->gestionnaire = $this->createUtilisateur(['ROLE_GESTIONNAIRE'], 'sophie@test.local');
        $this->admin = $this->createUtilisateur(['ROLE_ADMIN'], 'admin@test.local');

        // Creer les operations
        $this->operationAssignee = new Operation();
        $this->operationAssignee->setMatricule('TEST-001');
        $this->operationAssignee->setNom('Operation Assignee');
        $this->operationAssignee->setTechnicienAssigne($this->technicien);

        $this->operationNonAssignee = new Operation();
        $this->operationNonAssignee->setMatricule('TEST-002');
        $this->operationNonAssignee->setNom('Operation Non Assignee');
    }

    private function createUtilisateur(array $roles, string $email): Utilisateur
    {
        $user = new Utilisateur();
        $user->setEmail($email);
        $user->setNom('Test');
        $user->setPrenom('User');
        $user->setRoles($roles);
        $user->setPassword('hashed_password');

        return $user;
    }

    private function createToken(Utilisateur $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    /**
     * Test que le voter supporte les bonnes operations.
     */
    public function testSupportsViewAndEditOnOperation(): void
    {
        // Le voter doit supporter view et edit sur Operation
        $token = $this->createToken($this->technicien);

        // Test VIEW
        $result = $this->voter->vote($token, $this->operationAssignee, ['view']);
        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, $result);

        // Test EDIT
        $result = $this->voter->vote($token, $this->operationAssignee, ['edit']);
        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /**
     * Test que le voter s'abstient sur les mauvais attributs.
     */
    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $token = $this->createToken($this->technicien);

        $result = $this->voter->vote($token, $this->operationAssignee, ['delete']);
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /**
     * Test que le voter s'abstient sur les mauvais sujets.
     */
    public function testAbstainsOnUnsupportedSubject(): void
    {
        $token = $this->createToken($this->technicien);

        $result = $this->voter->vote($token, 'not_an_operation', ['view']);
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /**
     * Test qu'un technicien peut voir son operation assignee.
     */
    public function testTechnicienCanViewAssignedOperation(): void
    {
        $token = $this->createToken($this->technicien);

        $result = $this->voter->vote($token, $this->operationAssignee, ['view']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test qu'un technicien peut modifier son operation assignee.
     */
    public function testTechnicienCanEditAssignedOperation(): void
    {
        $token = $this->createToken($this->technicien);

        $result = $this->voter->vote($token, $this->operationAssignee, ['edit']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test qu'un technicien ne peut pas voir une operation non assignee.
     */
    public function testTechnicienCannotViewUnassignedOperation(): void
    {
        $token = $this->createToken($this->technicien);

        $result = $this->voter->vote($token, $this->operationNonAssignee, ['view']);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    /**
     * Test qu'un technicien ne peut pas modifier une operation non assignee.
     */
    public function testTechnicienCannotEditUnassignedOperation(): void
    {
        $token = $this->createToken($this->technicien);

        $result = $this->voter->vote($token, $this->operationNonAssignee, ['edit']);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    /**
     * Test qu'un technicien ne peut pas voir une operation assignee a un autre.
     */
    public function testTechnicienCannotViewOtherTechnicienOperation(): void
    {
        // Operation assignee a autreTechnicien
        $this->operationAssignee->setTechnicienAssigne($this->autreTechnicien);

        $token = $this->createToken($this->technicien);

        $result = $this->voter->vote($token, $this->operationAssignee, ['view']);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    /**
     * Test qu'un gestionnaire peut voir n'importe quelle operation.
     */
    public function testGestionnaireCanViewAnyOperation(): void
    {
        $token = $this->createToken($this->gestionnaire);

        // Operation assignee
        $result = $this->voter->vote($token, $this->operationAssignee, ['view']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);

        // Operation non assignee
        $result = $this->voter->vote($token, $this->operationNonAssignee, ['view']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test qu'un gestionnaire peut modifier n'importe quelle operation.
     */
    public function testGestionnaireCanEditAnyOperation(): void
    {
        $token = $this->createToken($this->gestionnaire);

        $result = $this->voter->vote($token, $this->operationAssignee, ['edit']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);

        $result = $this->voter->vote($token, $this->operationNonAssignee, ['edit']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test qu'un admin peut voir n'importe quelle operation.
     */
    public function testAdminCanViewAnyOperation(): void
    {
        $token = $this->createToken($this->admin);

        $result = $this->voter->vote($token, $this->operationAssignee, ['view']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);

        $result = $this->voter->vote($token, $this->operationNonAssignee, ['view']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test qu'un admin peut modifier n'importe quelle operation.
     */
    public function testAdminCanEditAnyOperation(): void
    {
        $token = $this->createToken($this->admin);

        $result = $this->voter->vote($token, $this->operationAssignee, ['edit']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);

        $result = $this->voter->vote($token, $this->operationNonAssignee, ['edit']);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test qu'un utilisateur non authentifie ne peut rien faire.
     */
    public function testAnonymousUserCannotAccess(): void
    {
        // Creer un token avec un mock qui n'est pas un Utilisateur
        $mockToken = $this->createMock(UsernamePasswordToken::class);
        $mockToken->method('getUser')->willReturn(null);

        $result = $this->voter->vote($mockToken, $this->operationAssignee, ['view']);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }
}
