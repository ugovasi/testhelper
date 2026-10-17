<?php

use App\Models\RuoloModel;
use App\Models\UtenteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class AuthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private function creaUtente(string $email, string $password, bool $attivo = true, ?int $ruoloId = null): int
    {
        $model = new UtenteModel();

        return $model->insert([
            'nome'          => 'Utente di Test',
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'attivo'        => $attivo ? 1 : 0,
            'ruolo_id'      => $ruoloId,
        ]);
    }

    private function csrfField(): array
    {
        return [Services::security()->getTokenName() => csrf_hash()];
    }

    public function testLoginConCredenzialiValideCreaLaSessione(): void
    {
        $this->creaUtente('mario@example.com', 'Password123!');

        $result = $this->withSession()->post('login', array_merge([
            'email'    => 'mario@example.com',
            'password' => 'Password123!',
        ], $this->csrfField()));

        $result->assertRedirectTo(site_url('admin/dashboard'));
        $this->assertTrue(session()->get('logged_in'));
        $this->assertSame('mario@example.com', session()->get('email'));
    }

    public function testLoginConPasswordErrataNonCreaLaSessione(): void
    {
        $this->creaUtente('mario@example.com', 'Password123!');

        $result = $this->withSession()->post('login', array_merge([
            'email'    => 'mario@example.com',
            'password' => 'PasswordErrata',
        ], $this->csrfField()));

        $result->assertRedirect();
        $this->assertNull(session()->get('logged_in'));
    }

    public function testLoginConUtenteInesistenteNonCreaLaSessione(): void
    {
        $result = $this->withSession()->post('login', array_merge([
            'email'    => 'assente@example.com',
            'password' => 'Qualsiasi123!',
        ], $this->csrfField()));

        $result->assertRedirect();
        $this->assertNull(session()->get('logged_in'));
    }

    public function testLoginConUtenteDisattivatoNonCreaLaSessione(): void
    {
        $this->creaUtente('mario@example.com', 'Password123!', attivo: false);

        $result = $this->withSession()->post('login', array_merge([
            'email'    => 'mario@example.com',
            'password' => 'Password123!',
        ], $this->csrfField()));

        $result->assertRedirect();
        $this->assertNull(session()->get('logged_in'));
    }

    public function testLoginPopolaIPermessiDelRuoloInSessione(): void
    {
        $ruoloModel = new RuoloModel();
        $ruoloId    = $ruoloModel->insert(['nome' => 'Editor']);

        $permessoModel = new App\Models\PermessoModel();
        $permessoId     = $permessoModel->insert(['chiave' => 'utenti.view']);
        $ruoloModel->sincronizzaPermessi($ruoloId, [$permessoId]);

        $this->creaUtente('mario@example.com', 'Password123!', attivo: true, ruoloId: $ruoloId);

        $this->withSession()->post('login', array_merge([
            'email'    => 'mario@example.com',
            'password' => 'Password123!',
        ], $this->csrfField()));

        $this->assertSame(['utenti.view'], session()->get('permessi'));
    }

    public function testLogoutReindirizzaAlLoginConMessaggioDiConferma(): void
    {
        // NOTA: in ambiente "testing" CodeIgniter rende Session::destroy() un no-op
        // (vedi vendor/codeigniter4/framework/system/Session/Session.php), quindi qui
        // verifichiamo il comportamento osservabile del controller (redirect + flash)
        // invece di controllare lo svuotamento effettivo della sessione.
        $this->creaUtente('mario@example.com', 'Password123!');

        $this->withSession()->post('login', array_merge([
            'email'    => 'mario@example.com',
            'password' => 'Password123!',
        ], $this->csrfField()));

        $result = $this->withSession()->post('logout', $this->csrfField());

        $result->assertRedirectTo(site_url('login'));
        $result->assertSessionHas('success', 'Logout effettuato.');
    }
}
