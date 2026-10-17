<?php

use App\Models\PermessoModel;
use App\Models\RuoloModel;
use App\Models\UtenteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class RuoloModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    public function testInsertValidoVaABuonFine(): void
    {
        $model = new RuoloModel();

        $id = $model->insert(['nome' => 'Supervisore', 'descrizione' => 'Ruolo di supervisione']);

        $this->assertIsInt($id);
        $this->seeInDatabase('ruoli', ['nome' => 'Supervisore']);
    }

    public function testNomeDuplicatoFalliceLaValidazione(): void
    {
        $model = new RuoloModel();
        $model->insert(['nome' => 'Supervisore']);

        $risultato = $model->insert(['nome' => 'Supervisore']);

        $this->assertFalse($risultato);
        $this->assertArrayHasKey('nome', $model->errors());
    }

    public function testNomeTroppoCortoFalliceLaValidazione(): void
    {
        $model = new RuoloModel();

        $risultato = $model->insert(['nome' => 'A']);

        $this->assertFalse($risultato);
        $this->assertArrayHasKey('nome', $model->errors());
    }

    public function testGetPermessiRestituisceLeChiaviAssegnate(): void
    {
        $ruoloModel    = new RuoloModel();
        $permessoModel = new PermessoModel();

        $ruoloId     = $ruoloModel->insert(['nome' => 'Editor']);
        $permessoId1 = $permessoModel->insert(['chiave' => 'articoli.view']);
        $permessoId2 = $permessoModel->insert(['chiave' => 'articoli.edit']);

        $ruoloModel->sincronizzaPermessi($ruoloId, [$permessoId1, $permessoId2]);

        $chiavi = $ruoloModel->getPermessi($ruoloId);

        $this->assertCount(2, $chiavi);
        $this->assertContains('articoli.view', $chiavi);
        $this->assertContains('articoli.edit', $chiavi);
    }

    public function testSincronizzaPermessiSostituisceIPrecedenti(): void
    {
        $ruoloModel    = new RuoloModel();
        $permessoModel = new PermessoModel();

        $ruoloId     = $ruoloModel->insert(['nome' => 'Editor']);
        $permessoId1 = $permessoModel->insert(['chiave' => 'articoli.view']);
        $permessoId2 = $permessoModel->insert(['chiave' => 'articoli.edit']);

        $ruoloModel->sincronizzaPermessi($ruoloId, [$permessoId1]);
        $ruoloModel->sincronizzaPermessi($ruoloId, [$permessoId2]);

        $chiavi = $ruoloModel->getPermessi($ruoloId);

        $this->assertSame(['articoli.edit'], $chiavi);
    }

    public function testSincronizzaPermessiConArrayVuotoRimuoveTutto(): void
    {
        $ruoloModel    = new RuoloModel();
        $permessoModel = new PermessoModel();

        $ruoloId    = $ruoloModel->insert(['nome' => 'Editor']);
        $permessoId = $permessoModel->insert(['chiave' => 'articoli.view']);

        $ruoloModel->sincronizzaPermessi($ruoloId, [$permessoId]);
        $ruoloModel->sincronizzaPermessi($ruoloId, []);

        $this->assertSame([], $ruoloModel->getPermessi($ruoloId));
    }

    public function testUtentiAssegnatiContaSoloGliUtentiDelRuolo(): void
    {
        $ruoloModel  = new RuoloModel();
        $utenteModel = new UtenteModel();

        $ruoloId1 = $ruoloModel->insert(['nome' => 'RuoloA']);
        $ruoloId2 = $ruoloModel->insert(['nome' => 'RuoloB']);

        $utenteModel->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'ruolo_id' => $ruoloId1, 'attivo' => 1,
        ]);
        $utenteModel->insert([
            'nome' => 'Luca Bianchi', 'email' => 'luca@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'ruolo_id' => $ruoloId2, 'attivo' => 1,
        ]);

        $this->assertSame(1, $ruoloModel->utentiAssegnati($ruoloId1));
        $this->assertSame(0, $ruoloModel->utentiAssegnati(999999));
    }

    public function testUtentiAssegnatiIgnoraGliUtentiCancellati(): void
    {
        $ruoloModel  = new RuoloModel();
        $utenteModel = new UtenteModel();

        $ruoloId    = $ruoloModel->insert(['nome' => 'RuoloA']);
        $utenteId   = $utenteModel->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'ruolo_id' => $ruoloId,
        ]);

        $this->assertSame(1, $ruoloModel->utentiAssegnati($ruoloId));

        $utenteModel->delete($utenteId);

        // L'utente e' solo cancellato logicamente, ma non deve piu' bloccare
        // l'eliminazione del ruolo: vedi RuoloModel::utentiAssegnati().
        $this->assertSame(0, $ruoloModel->utentiAssegnati($ruoloId));
    }

    public function testConCreatoreModificatoreRisolveINomiDiChiHaOperato(): void
    {
        $utenteModel = new UtenteModel();
        $creatoreId  = $utenteModel->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);
        $modificatoreId = $utenteModel->insert([
            'nome' => 'Luca Bianchi', 'email' => 'luca@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $ruoloModel = new RuoloModel();
        $ruoloId    = $ruoloModel->insert(['nome' => 'Editor']);
        // created_by/updated_by non sono in $allowedFields (sono di sistema, vedi
        // BaseModel): li impostiamo qui direttamente per isolare il test del join,
        // a prescindere da chi li valorizzerebbe normalmente in una request reale.
        $ruoloModel->builder()->where('id', $ruoloId)->update(['created_by' => $creatoreId, 'updated_by' => $modificatoreId]);

        $riga = $ruoloModel->conCreatoreModificatore()->where('ruoli.id', $ruoloId)->get()->getRowArray();

        $this->assertSame('Mario Rossi', $riga['creato_da_nome']);
        $this->assertSame('Luca Bianchi', $riga['modificato_da_nome']);
    }
}
