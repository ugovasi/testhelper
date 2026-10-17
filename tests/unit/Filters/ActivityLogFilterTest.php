<?php

use App\Models\LogAttivitaModel;
use App\Models\RuoloModel;
use App\Models\UtenteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Verifica che App\Filters\ActivityLogFilter, registrato come filtro globale
 * 'after' in Config\Filters, scriva effettivamente una riga in log_attivita per
 * le operazioni di scrittura reali (via HTTP, non chiamando il filtro in isolamento),
 * cosi' il test copre anche la sua registrazione nella configurazione.
 *
 * @internal
 */
final class ActivityLogFilterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private function csrfField(): array
    {
        return [Services::security()->getTokenName() => csrf_hash()];
    }

    private function creaUtenteAdmin(string $email = 'admin@example.com', string $password = 'Password123!'): int
    {
        return (new UtenteModel())->insert([
            'nome'          => 'Amministratore',
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'attivo'        => 1,
        ]);
    }

    private function sessioneAmministratore(int $utenteId): array
    {
        return [
            'logged_in'  => true,
            'user_id'    => $utenteId,
            'nome'       => 'Amministratore',
            'email'      => 'admin@example.com',
            'permessi'   => ['ruoli.view', 'ruoli.create', 'ruoli.edit', 'ruoli.delete'],
        ];
    }

    public function testLoginRiuscitoRegistraUnaRigaDiLog(): void
    {
        $this->creaUtenteAdmin();

        $this->withSession()->post('login', array_merge([
            'email'    => 'admin@example.com',
            'password' => 'Password123!',
        ], $this->csrfField()));

        $righe = (new LogAttivitaModel())->orderBy('id', 'desc')->first();

        $this->assertNotNull($righe);
        $this->assertSame('login', $righe['azione']);
        $this->assertSame('auth', $righe['modulo']);
        $this->assertSame(1, (int) $righe['esito']);
        $this->assertSame('admin@example.com', $righe['utente_email']);
        $this->assertSame('Login effettuato', $righe['descrizione']);
    }

    public function testLoginFallitoRegistraUnaRigaConEsitoZero(): void
    {
        $this->creaUtenteAdmin();

        $this->withSession()->post('login', array_merge([
            'email'    => 'admin@example.com',
            'password' => 'PasswordErrata',
        ], $this->csrfField()));

        $riga = (new LogAttivitaModel())->orderBy('id', 'desc')->first();

        $this->assertNotNull($riga);
        $this->assertSame('login', $riga['azione']);
        $this->assertSame(0, (int) $riga['esito']);
        $this->assertNull($riga['utente_id']);
        // L'email non e' nella sessione (login fallito): viene presa dal form.
        $this->assertSame('admin@example.com', $riga['utente_email']);
    }

    public function testCreazioneRuoloRegistraChiCosaEQuando(): void
    {
        $utenteId = $this->creaUtenteAdmin();

        $this->withSession($this->sessioneAmministratore($utenteId))->post('admin/ruoli/store', array_merge([
            'nome' => 'Supervisore',
        ], $this->csrfField()));

        $riga = (new LogAttivitaModel())->orderBy('id', 'desc')->first();

        $this->assertNotNull($riga);
        $this->assertSame($utenteId, $riga['utente_id']);
        $this->assertSame('Amministratore', $riga['utente_nome']);
        $this->assertSame('create', $riga['azione']);
        $this->assertSame('ruoli', $riga['modulo']);
        $this->assertSame(1, (int) $riga['esito']);
        $this->assertStringContainsString('Supervisore', $riga['descrizione']);
        $this->assertNotNull($riga['created_at']);
    }

    public function testModificaRuoloRegistraLidEntitaCoinvolta(): void
    {
        $utenteId = $this->creaUtenteAdmin();
        $ruoloId  = (new RuoloModel())->insert(['nome' => 'Editor']);

        $this->withSession($this->sessioneAmministratore($utenteId))->post("admin/ruoli/update/{$ruoloId}", array_merge([
            'nome' => 'Editor Senior',
        ], $this->csrfField()));

        $riga = (new LogAttivitaModel())->orderBy('id', 'desc')->first();

        $this->assertSame('update', $riga['azione']);
        $this->assertSame($ruoloId, $riga['entita_id']);
        $this->assertStringContainsString((string) $ruoloId, $riga['descrizione']);
    }

    public function testEliminazioneRuoloRegistraLoperazione(): void
    {
        $utenteId = $this->creaUtenteAdmin();
        $ruoloId  = (new RuoloModel())->insert(['nome' => 'Editor']);

        $this->withSession($this->sessioneAmministratore($utenteId))
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-CSRF-TOKEN'     => csrf_hash(),
            ])
            ->delete("admin/ruoli/delete/{$ruoloId}");

        $riga = (new LogAttivitaModel())->orderBy('id', 'desc')->first();

        $this->assertSame('delete', $riga['azione']);
        $this->assertSame('ruoli', $riga['modulo']);
        $this->assertSame($ruoloId, $riga['entita_id']);
    }

    public function testLaConsultazioneDellElencoNonProduceRigheDiLog(): void
    {
        $utenteId = $this->creaUtenteAdmin();

        $this->withSession($this->sessioneAmministratore($utenteId))->get('admin/ruoli');
        $this->withSession($this->sessioneAmministratore($utenteId))->post('admin/ruoli/datatable', $this->csrfField());

        // index e datatable sono letture, non operazioni: nessuna riga attesa.
        $this->assertSame(0, (new LogAttivitaModel())->countAllResults());
    }
}
