<?php

namespace App\Models;

use CodeIgniter\Model;

class PassaggioModel extends Model
{
    protected $table            = 'passaggi';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['caso_id', 'ordine', 'azione', 'risultato_atteso', 'stima_minuti'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Scritto solo da CasoTestModel::sincronizzaPassaggi(), che valida a monte.
    protected $skipValidation = true;

    public function haEsecuzioni(int $passaggioId): bool
    {
        return $this->db->table('esecuzioni_passaggio')->where('passaggio_id', $passaggioId)->countAllResults() > 0;
    }
}
