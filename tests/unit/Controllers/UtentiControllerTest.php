<?php

use App\Models\UtenteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class UtentiControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private function csrfField(): array
    {
        return [Services::security()->getTokenName() => csrf_hash()];
    }

    private function creaUtenteAdmin(): int
    {
        return (new UtenteModel())->insert([
            'nome'          => 'Amministratore',
            'email'         => 'admin@example.com',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'attivo'        => 1,
        ]);
    }

    private function sessioneAmministratore(int $utenteId): array
    {
        return [
            'logged_in' => true,
            'user_id'   => $utenteId,
            'nome'      => 'Amministratore',
            'email'     => 'admin@example.com',
            'permessi'  => ['utenti.view', 'utenti.create', 'utenti.edit', 'utenti.delete'],
        ];
    }

    public function testStoreValorizzaCreatedByConLutenteInSessione(): void
    {
        $adminId = $this->creaUtenteAdmin();

        $this->withSession($this->sessioneAmministratore($adminId))->post('admin/utenti/store', array_merge([
            'nome'     => 'Mario Rossi',
            'email'    => 'mario@example.com',
            'password' => 'Password123!',
        ], $this->csrfField()));

        $utente = (new UtenteModel())->findByEmail('mario@example.com');

        $this->assertSame($adminId, $utente['created_by']);
    }

    public function testDeleteEUnaCancellazioneLogicaChePersisteNelDatabase(): void
    {
        $adminId  = $this->creaUtenteAdmin();
        $model    = new UtenteModel();
        $utenteId = $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
        ]);

        $result = $this->withSession($this->sessioneAmministratore($adminId))
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-CSRF-TOKEN'     => csrf_hash(),
            ])
            ->delete("admin/utenti/delete/{$utenteId}");

        $result->assertJSONFragment(['success' => true]);

        // La riga esiste ancora (cancellazione logica, non DELETE definitivo).
        $this->seeInDatabase('utenti', ['id' => $utenteId, 'email' => 'mario@example.com']);
        $rigaAncoraPresente = $model->withDeleted()->find($utenteId);
        $this->assertNotNull($rigaAncoraPresente['deleted_at']);
    }

    public function testDatatableEspineIlNomeDiChiHaCreatoEModificato(): void
    {
        $adminId = $this->creaUtenteAdmin();
        $model   = new UtenteModel();
        session()->set('user_id', $adminId);
        $utenteId = $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
        ]);

        $result = $this->withSession($this->sessioneAmministratore($adminId))
            ->post('admin/utenti/datatable', $this->csrfField());

        $json = json_decode($result->getJSON(), true);
        $riga = current(array_filter($json['data'], static fn ($r) => $r['id'] === $utenteId));

        $this->assertSame('Amministratore', $riga['creato_da_nome']);
        $this->assertSame('Amministratore', $riga['modificato_da_nome']);
    }

    public function testUnUtenteCancellatoNonCompareNellaDatatable(): void
    {
        $adminId  = $this->creaUtenteAdmin();
        $model    = new UtenteModel();
        $utenteId = $model->insert([
            'nome' => 'Mario Rossi', 'email' => 'mario@example.com',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
        ]);
        $model->delete($utenteId);

        $result = $this->withSession($this->sessioneAmministratore($adminId))
            ->post('admin/utenti/datatable', $this->csrfField());

        $json = json_decode($result->getJSON(), true);

        $this->assertSame(1, $json['recordsTotal']); // solo l'amministratore
        $this->assertSame([$adminId], array_column($json['data'], 'id'));
    }
}
