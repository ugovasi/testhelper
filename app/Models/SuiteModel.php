<?php

namespace App\Models;

class SuiteModel extends BaseModel
{
    protected $table            = 'suite';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['prodotto_id', 'nome', 'descrizione'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'prodotto_id' => 'required|is_natural_no_zero|is_not_unique[prodotti.id]',
        'nome'        => 'required|min_length[2]|max_length[100]',
        'descrizione' => 'permit_empty|max_length[255]',
    ];

    protected $skipValidation = false;

    public function casiCollegati(int $suiteId): int
    {
        return $this->db->table('casi_test')->where('suite_id', $suiteId)->countAllResults();
    }
}
