<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Espone l'hash CSRF corrente in un header di risposta su OGNI richiesta.
 * Con `security.regenerate = true` il token cambia a ogni submit protetto: il JS dei
 * form modali legge questo header dopo ciascuna chiamata AJAX e aggiorna il token che
 * usera' per la richiesta successiva, cosi' una pagina con piu' submit consecutivi
 * (crea, poi modifica, poi elimina) non incappa mai in un token scaduto.
 */
class CsrfTokenFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-CSRF-TOKEN', csrf_hash());
    }
}
