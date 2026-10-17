<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Blocca l'accesso alle route protette se non c'e' una sessione autenticata.
 * Le richieste AJAX ricevono un 401 JSON invece di un redirect, cosi' il
 * JS lato client puo' reindirizzare al login senza mostrare una pagina rotta.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('logged_in')) {
            return;
        }

        if ($request->isAJAX()) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['success' => false, 'message' => 'Sessione scaduta, effettua di nuovo il login.']);
        }

        session()->set('redirect_url', current_url());

        return redirect()->to(site_url('login'))->with('error', 'Devi effettuare il login per accedere a questa pagina.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
