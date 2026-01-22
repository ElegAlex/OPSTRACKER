<?php

namespace App\Tests\Unit\Service;

use App\Entity\Operation;
use App\Entity\TypeOperation;
use App\Service\ChampPersonnaliseService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ChampPersonnaliseService.
 *
 * Regles metier testees :
 * - RG-061 : 5 types de champs personnalises
 * - RG-015 : Validation des donnees JSONB
 */
class ChampPersonnaliseServiceTest extends TestCase
{
    private ChampPersonnaliseService $service;

    protected function setUp(): void
    {
        $this->service = new ChampPersonnaliseService();
    }

    public function testTypesDisponibles(): void
    {
        $types = ChampPersonnaliseService::TYPES;

        $this->assertCount(5, $types);
        $this->assertArrayHasKey(ChampPersonnaliseService::TYPE_TEXTE_COURT, $types);
        $this->assertArrayHasKey(ChampPersonnaliseService::TYPE_TEXTE_LONG, $types);
        $this->assertArrayHasKey(ChampPersonnaliseService::TYPE_NOMBRE, $types);
        $this->assertArrayHasKey(ChampPersonnaliseService::TYPE_DATE, $types);
        $this->assertArrayHasKey(ChampPersonnaliseService::TYPE_LISTE, $types);
    }

    public function testCreerChampVide(): void
    {
        $champ = $this->service->creerChampVide();

        $this->assertArrayHasKey('code', $champ);
        $this->assertArrayHasKey('label', $champ);
        $this->assertArrayHasKey('type', $champ);
        $this->assertArrayHasKey('obligatoire', $champ);
        $this->assertArrayHasKey('options', $champ);
        $this->assertEmpty($champ['code']);
        $this->assertFalse($champ['obligatoire']);
    }

    public function testValiderChampCodeManquant(): void
    {
        $champ = [
            'code' => '',
            'label' => 'Test',
            'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT,
        ];

        $erreurs = $this->service->validerChamp($champ);

        $this->assertNotEmpty($erreurs);
        $this->assertStringContainsString('code', $erreurs[0]);
    }

    public function testValiderChampCodeInvalide(): void
    {
        $champ = [
            'code' => 'Invalid-Code',
            'label' => 'Test',
            'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT,
        ];

        $erreurs = $this->service->validerChamp($champ);

        $this->assertNotEmpty($erreurs);
        $this->assertStringContainsString('minuscule', $erreurs[0]);
    }

    public function testValiderChampLabelManquant(): void
    {
        $champ = [
            'code' => 'test_code',
            'label' => '',
            'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT,
        ];

        $erreurs = $this->service->validerChamp($champ);

        $this->assertNotEmpty($erreurs);
        $this->assertStringContainsString('libellé', $erreurs[0]);
    }

    public function testValiderChampTypeInvalide(): void
    {
        $champ = [
            'code' => 'test_code',
            'label' => 'Test',
            'type' => 'type_inexistant',
        ];

        $erreurs = $this->service->validerChamp($champ);

        $this->assertNotEmpty($erreurs);
        $this->assertStringContainsString('invalide', $erreurs[0]);
    }

    public function testValiderChampListeSansOptions(): void
    {
        $champ = [
            'code' => 'test_code',
            'label' => 'Test',
            'type' => ChampPersonnaliseService::TYPE_LISTE,
            'options' => [],
        ];

        $erreurs = $this->service->validerChamp($champ);

        $this->assertNotEmpty($erreurs);
        $this->assertStringContainsString('option', $erreurs[0]);
    }

    public function testValiderChampValide(): void
    {
        $champ = [
            'code' => 'numero_serie',
            'label' => 'Numéro de série',
            'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT,
            'obligatoire' => true,
        ];

        $erreurs = $this->service->validerChamp($champ);

        $this->assertEmpty($erreurs);
    }

    public function testValiderChampListeValide(): void
    {
        $champ = [
            'code' => 'statut_materiel',
            'label' => 'Statut du matériel',
            'type' => ChampPersonnaliseService::TYPE_LISTE,
            'options' => ['Neuf', 'Occasion', 'Reconditionné'],
        ];

        $erreurs = $this->service->validerChamp($champ);

        $this->assertEmpty($erreurs);
    }

    public function testValiderChampsTypeOperationCodesDupliques(): void
    {
        $typeOperation = new TypeOperation();
        $typeOperation->setNom('Test');
        $typeOperation->setChampsPersonnalises([
            ['code' => 'test', 'label' => 'Test 1', 'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT],
            ['code' => 'test', 'label' => 'Test 2', 'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT],
        ]);

        $erreurs = $this->service->validerChampsTypeOperation($typeOperation);

        $this->assertNotEmpty($erreurs);
        $this->assertTrue(
            array_reduce($erreurs, fn($carry, $e) => $carry || str_contains($e, 'déjà utilisé'), false)
        );
    }

    public function testValiderValeurTexteCourt(): void
    {
        $champ = [
            'code' => 'test',
            'label' => 'Test',
            'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT,
            'obligatoire' => false,
        ];

        $this->assertNull($this->service->validerValeur($champ, 'Valeur OK'));
        $this->assertNull($this->service->validerValeur($champ, ''));

        // Texte trop long
        $valeurTropLongue = str_repeat('a', 256);
        $erreur = $this->service->validerValeur($champ, $valeurTropLongue);
        $this->assertNotNull($erreur);
        $this->assertStringContainsString('255', $erreur);
    }

    public function testValiderValeurObligatoire(): void
    {
        $champ = [
            'code' => 'test',
            'label' => 'Test',
            'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT,
            'obligatoire' => true,
        ];

        $erreur = $this->service->validerValeur($champ, '');
        $this->assertNotNull($erreur);
        $this->assertStringContainsString('obligatoire', $erreur);

        $this->assertNull($this->service->validerValeur($champ, 'Valeur'));
    }

    public function testValiderValeurNombre(): void
    {
        $champ = [
            'code' => 'quantite',
            'label' => 'Quantité',
            'type' => ChampPersonnaliseService::TYPE_NOMBRE,
            'obligatoire' => false,
        ];

        $this->assertNull($this->service->validerValeur($champ, 42));
        $this->assertNull($this->service->validerValeur($champ, '42.5'));
        $this->assertNull($this->service->validerValeur($champ, ''));

        $erreur = $this->service->validerValeur($champ, 'pas un nombre');
        $this->assertNotNull($erreur);
        $this->assertStringContainsString('nombre', $erreur);
    }

    public function testValiderValeurDate(): void
    {
        $champ = [
            'code' => 'date_installation',
            'label' => 'Date d\'installation',
            'type' => ChampPersonnaliseService::TYPE_DATE,
            'obligatoire' => false,
        ];

        $this->assertNull($this->service->validerValeur($champ, '2026-01-22'));
        $this->assertNull($this->service->validerValeur($champ, ''));

        $erreur = $this->service->validerValeur($champ, '22/01/2026');
        $this->assertNotNull($erreur);
        $this->assertStringContainsString('date', strtolower($erreur));

        $erreur = $this->service->validerValeur($champ, '2026-13-45');
        $this->assertNotNull($erreur);
    }

    public function testValiderValeurListe(): void
    {
        $champ = [
            'code' => 'statut',
            'label' => 'Statut',
            'type' => ChampPersonnaliseService::TYPE_LISTE,
            'options' => ['Neuf', 'Occasion', 'Reconditionné'],
            'obligatoire' => false,
        ];

        $this->assertNull($this->service->validerValeur($champ, 'Neuf'));
        $this->assertNull($this->service->validerValeur($champ, ''));

        $erreur = $this->service->validerValeur($champ, 'Inconnu');
        $this->assertNotNull($erreur);
        $this->assertStringContainsString('options', $erreur);
    }

    public function testParseOptions(): void
    {
        $options = $this->service->parseOptions('Option 1, Option 2,  Option 3 ');

        $this->assertCount(3, $options);
        $this->assertEquals('Option 1', $options[0]);
        $this->assertEquals('Option 2', $options[1]);
        $this->assertEquals('Option 3', $options[2]);
    }

    public function testParseOptionsVide(): void
    {
        $options = $this->service->parseOptions('');

        $this->assertEmpty($options);
    }

    public function testOptionsToString(): void
    {
        $string = $this->service->optionsToString(['A', 'B', 'C']);

        $this->assertEquals('A, B, C', $string);
    }

    public function testGenererInputHtmlTexteCourt(): void
    {
        $champ = [
            'code' => 'test',
            'label' => 'Test',
            'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT,
            'obligatoire' => false,
        ];

        $html = $this->service->genererInputHtml($champ, 'valeur');

        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('name="test"', $html);
        $this->assertStringContainsString('value="valeur"', $html);
        $this->assertStringContainsString('maxlength="255"', $html);
    }

    public function testGenererInputHtmlTexteLong(): void
    {
        $champ = [
            'code' => 'description',
            'label' => 'Description',
            'type' => ChampPersonnaliseService::TYPE_TEXTE_LONG,
            'obligatoire' => false,
        ];

        $html = $this->service->genererInputHtml($champ, 'contenu');

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('contenu</textarea>', $html);
    }

    public function testGenererInputHtmlNombre(): void
    {
        $champ = [
            'code' => 'quantite',
            'label' => 'Quantité',
            'type' => ChampPersonnaliseService::TYPE_NOMBRE,
            'obligatoire' => true,
        ];

        $html = $this->service->genererInputHtml($champ, 42);

        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('value="42"', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function testGenererInputHtmlDate(): void
    {
        $champ = [
            'code' => 'date_test',
            'label' => 'Date',
            'type' => ChampPersonnaliseService::TYPE_DATE,
            'obligatoire' => false,
        ];

        $html = $this->service->genererInputHtml($champ, '2026-01-22');

        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('value="2026-01-22"', $html);
    }

    public function testGenererInputHtmlListe(): void
    {
        $champ = [
            'code' => 'statut',
            'label' => 'Statut',
            'type' => ChampPersonnaliseService::TYPE_LISTE,
            'options' => ['A', 'B', 'C'],
            'obligatoire' => false,
        ];

        $html = $this->service->genererInputHtml($champ, 'B');

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('<option value="A"', $html);
        $this->assertStringContainsString('<option value="B" selected', $html);
        $this->assertStringContainsString('<option value="C"', $html);
    }

    public function testGenererInputHtmlAvecPrefix(): void
    {
        $champ = [
            'code' => 'test',
            'label' => 'Test',
            'type' => ChampPersonnaliseService::TYPE_TEXTE_COURT,
            'obligatoire' => false,
        ];

        $html = $this->service->genererInputHtml($champ, '', 'donnees');

        $this->assertStringContainsString('name="donnees[test]"', $html);
    }
}
