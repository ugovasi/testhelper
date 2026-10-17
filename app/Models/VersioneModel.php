<?php

namespace App\Models;

class VersioneModel extends BaseModel
{
    protected $table            = 'versioni';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['prodotto_id', 'nome', 'data_rilascio_prevista'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'prodotto_id'            => 'required|is_natural_no_zero|is_not_unique[prodotti.id]',
        'nome'                   => 'required|max_length[50]',
        'data_rilascio_prevista' => 'permit_empty|valid_date[Y-m-d]',
    ];

    protected $skipValidation = false;

    public function pianiCollegati(int $versioneId): int
    {
        return $this->db->table('piani_test')->where('versione_id', $versioneId)->countAllResults();
    }
}
