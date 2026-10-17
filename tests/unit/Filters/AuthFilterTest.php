<?php

use App\Filters\AuthFilter;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FilterTestTrait;

/**
 * @internal
 */
final class AuthFilterTest extends CIUnitTestCase
{
    use FilterTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilterTestTrait();
        session()->destroy();
    }

    public function testUtenteNonAutenticatoVieneReindirizzatoAlLogin(): void
    {
        $result = $this->getFilterCaller(new AuthFilter(), 'before')();

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('login', $result->getHeaderLine('Location'));
    }

    public function testUtenteAutenticatoPuoProseguire(): void
    {
        session()->set('logged_in', true);

        $result = $this->getFilterCaller(new AuthFilter(), 'before')();

        $this->assertNull($result);
    }

    public function testRichiestaAjaxNonAutenticataRicevveUn401Json(): void
    {
        $this->request->setHeader('X-Requested-With', 'XMLHttpRequest');

        $result = $this->getFilterCaller(new AuthFilter(), 'before')();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(401, $result->getStatusCode());
    }
}
