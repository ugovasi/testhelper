<?php

use App\Models\RuoloModel;
use App\Models\UtenteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class UtenteModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    public function testInsertValidoVaABuonFine(): void
    {
        $model = new UtenteModel();

        $id = $model->insert([
            'nome'          => 'Mario Rossi',
            'email'         => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $this->assertIsInt($id);
        $this->seeInDatabase('utenti', ['email' => 'mario@example.com']);
    }

    public function testEmailDuplicataFalliceLaValidazione(): void
    {
        $model = new UtenteModel();
        $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $risultato = $model->insert([
            'nome' => 'Altro Utente', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $this->assertFalse($risultato);
        $this->assertArrayHasKey('email', $model->errors());
    }

    public function testEmailNonValidaFalliceLaValidazione(): void
    {
        $model = new UtenteModel();

        $risultato = $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'non-una-email',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $this->assertFalse($risultato);
        $this->assertArrayHasKey('email', $model->errors());
    }

    public function testFindByEmailRestituisceLutenteCorretto(): void
    {
        $model = new UtenteModel();
        $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $trovato = $model->findByEmail('mario@example.com');

        $this->assertNotNull($trovato);
        $this->assertSame('Mario Rossi', $trovato['nome']);
    }

    public function testFindByEmailRestituisceNullSeAssente(): void
    {
        $model = new UtenteModel();

        $this->assertNull($model->findByEmail('assente@example.com'));
    }

    public function testConRuoloAggiungeIlNomeDelRuolo(): void
    {
        $ruoloModel  = new RuoloModel();
        $utenteModel = new UtenteModel();

        $ruoloId = $ruoloModel->insert(['nome' => 'Amministratore']);
        $utenteModel->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'ruolo_id' => $ruoloId,
        ]);

        $risultato = $utenteModel->conRuolo()->where('email', 'mario@example.com')->first();

        $this->assertSame('Amministratore', $risultato['ruolo_nome']);
    }

    public function testAttivoDiDefaultEUno(): void
    {
        $model = new UtenteModel();
        $id    = $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $utente = $model->find($id);

        $this->assertSame(1, (int) $utente['attivo']);
    }

    public function testInsertValorizzaCreatedByEUpdatedByDallaSessione(): void
    {
        session()->set('user_id', 42);

        $model = new UtenteModel();
        $id    = $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $utente = $model->find($id);

        $this->assertSame(42, (int) $utente['created_by']);
        $this->assertSame(42, (int) $utente['updated_by']);
    }

    public function testUpdateValorizzaSoloUpdatedByLasciandoCreatedByInvariato(): void
    {
        session()->set('user_id', 1);
        $model = new UtenteModel();
        $id    = $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        session()->set('user_id', 2);
        $model->update($id, ['nome' => 'Mario Bianchi']);

        $utente = $model->find($id);

        $this->assertSame(1, (int) $utente['created_by']);
        $this->assertSame(2, (int) $utente['updated_by']);
    }

    public function testDeleteEUnaCancellazioneLogicaNonDefinitiva(): void
    {
        $model = new UtenteModel();
        $id    = $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $model->delete($id);

        // Sparisce dalle query "normali" del model...
        $this->assertNull($model->find($id));
        $this->assertNull($model->findByEmail('mario@example.com'));

        // ...ma la riga esiste ancora nel database, con deleted_at valorizzato.
        $this->seeInDatabase('utenti', ['id' => $id, 'email' => 'mario@example.com']);
        $riga = $model->withDeleted()->find($id);
        $this->assertNotNull($riga['deleted_at']);
    }

    public function testConRuoloEscludeGliUtentiCancellati(): void
    {
        $model = new UtenteModel();
        $id    = $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $model->delete($id);

        $risultato = $model->conRuolo()->where('utenti.id', $id)->get()->getResultArray();

        $this->assertSame([], $risultato);
    }
}
