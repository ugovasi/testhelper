<?php

use App\Models\PermessoModel;
use App\Models\RuoloModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class PermessoModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    public function testInsertValidoVaABuonFine(): void
    {
        $model = new PermessoModel();

        $id = $model->insert(['chiave' => 'utenti.view', 'descrizione' => 'Visualizza utenti']);

        $this->assertIsInt($id);
        $this->seeInDatabase('permessi', ['chiave' => 'utenti.view']);
    }

    public function testChiaveDuplicataFalliceLaValidazione(): void
    {
        $model = new PermessoModel();
        $model->insert(['chiave' => 'utenti.view']);

        $risultato = $model->insert(['chiave' => 'utenti.view']);

        $this->assertFalse($risultato);
        $this->assertArrayHasKey('chiave', $model->errors());
    }

    public function testChiaveConCaratteriNonAmmessiFalliceLaValidazione(): void
    {
        $model = new PermessoModel();

        $risultato = $model->insert(['chiave' => 'Utenti View!']);

        $this->assertFalse($risultato);
        $this->assertArrayHasKey('chiave', $model->errors());
    }

    public function testInUsoRestituisceFalseSeNonAssegnato(): void
    {
        $model = new PermessoModel();
        $id    = $model->insert(['chiave' => 'utenti.delete']);

        $this->assertFalse($model->inUso($id));
    }

    public function testInUsoRestituisceTrueSeAssegnatoAUnRuolo(): void
    {
        $permessoModel = new PermessoModel();
        $ruoloModel    = new RuoloModel();

        $permessoId = $permessoModel->insert(['chiave' => 'utenti.delete']);
        $ruoloId    = $ruoloModel->insert(['nome' => 'Amministratore']);

        $ruoloModel->sincronizzaPermessi($ruoloId, [$permessoId]);

        $this->assertTrue($permessoModel->inUso($permessoId));
    }
}
