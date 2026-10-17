<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\GuidaContenuti;

/**
 * Guida in linea: spiega le operazioni possibili all'utente in base ai permessi del
 * suo ruolo. Accessibile a ogni utente autenticato (nessun permesso richiesto): mostra
 * solo le sezioni pertinenti, vedi App\Libraries\GuidaContenuti.
 */
class GuidaController extends BaseController
{
    public function index()
    {
        $permessi = session()->get('permessi') ?? [];

        return view('admin/guida', [
            'title'    => 'Guida',
            'sezioni'  => GuidaContenuti::sezioniPer($permessi),
            'permessi' => $permessi,
        ]);
    }
}
