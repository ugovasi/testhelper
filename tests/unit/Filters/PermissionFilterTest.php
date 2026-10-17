<?php

use App\Filters\PermissionFilter;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FilterTestTrait;

/**
 * @internal
 */
final class PermissionFilterTest extends CIUnitTestCase
{
    use FilterTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilterTestTrait();
        session()->destroy();
    }

    public function testUtenteSenzaPermessoVieneReindirizzato(): void
    {
        session()->set('permessi', ['utenti.view']);

        $result = $this->getFilterCaller(new PermissionFilter(), 'before')(['utenti.delete']);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testUtenteConPermessoPuoProseguire(): void
    {
        session()->set('permessi', ['utenti.view', 'utenti.delete']);

        $result = $this->getFilterCaller(new PermissionFilter(), 'before')(['utenti.delete']);

        $this->assertNull($result);
    }

    public function testSenzaPermessiInSessioneVieneReindirizzato(): void
    {
        $result = $this->getFilterCaller(new PermissionFilter(), 'before')(['utenti.delete']);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testRichiestaAjaxSenzaPermessoRicevveUn403Json(): void
    {
        session()->set('permessi', []);
        $this->request->setHeader('X-Requested-With', 'XMLHttpRequest');

        $result = $this->getFilterCaller(new PermissionFilter(), 'before')(['utenti.delete']);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(403, $result->getStatusCode());
    }
}
