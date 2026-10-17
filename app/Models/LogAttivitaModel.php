<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registro di sola scrittura popolato automaticamente da App\Filters\ActivityLogFilter:
 * nessun controller inserisce righe qui direttamente, e non esiste update/delete da UI
 * (il log e' immutabile una volta scritto).
 */
class LogAttivitaModel extends Model
{
    protected $table            = 'log_attivita';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'utente_id', 'utente_nome', 'utente_email', 'azione', 'modulo',
        'entita_id', 'descrizione', 'esito', 'ip_address',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    // Le righe sono costruite internamente da ActivityLogFilter, non da input utente
    // passato a un form: non serve una validazione stile CRUD.
    protected $skipValidation = true;
}
