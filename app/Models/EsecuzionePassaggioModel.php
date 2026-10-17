<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Storico append-only delle esecuzioni: non esiste update/delete, un nuovo tentativo
 * e' sempre una nuova riga (vedi App\Libraries\EsecuzioneService).
 */
class EsecuzionePassaggioModel extends Model
{
    public const ESITI = ['passato', 'fallito', 'bloccato', 'saltato'];

    protected $table            = 'esecuzioni_passaggio';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['piano_caso_id', 'passaggio_id', 'tentativo', 'esito', 'note', 'minuti_impiegati', 'eseguito_da', 'eseguito_il'];

    protected $useTimestamps = false;

    // Scritto solo da EsecuzioneService, che valida esito e stato del passaggio.
    protected $skipValidation = true;
}
