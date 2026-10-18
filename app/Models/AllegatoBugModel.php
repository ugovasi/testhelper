<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Scritto solo da App\Libraries\AllegatiBug, che valida i file prima di salvarli.
 */
class AllegatoBugModel extends Model
{
    protected $table            = 'bug_allegati';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['bug_id', 'nome_originale', 'nome_file', 'mime', 'dimensione', 'caricato_da'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $skipValidation = true;
}
