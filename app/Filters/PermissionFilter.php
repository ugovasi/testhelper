<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtro RBAC: richiede che l'utente in sessione abbia una specifica chiave di
 * permesso, es. `filter => 'permission:utenti.edit'` nella route.
 * Va sempre applicato DOPO 'auth' cosi' la sessione e' gia' garantita.
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $permessoRichiesto = $arguments[0] ?? null;
        $permessiUtente    = session()->get('permessi') ?? [];

        if ($permessoRichiesto !== null && in_array($permessoRichiesto, $permessiUtente, true)) {
            return;
        }

        if ($request->isAJAX()) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['success' => false, 'message' => 'Non hai i permessi necessari per questa operazione.']);
        }

        return redirect()->to(site_url('admin/dashboard'))
            ->with('error', 'Non hai i permessi necessari per accedere a questa pagina.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
