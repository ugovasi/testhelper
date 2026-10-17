<?php

namespace App\Models;

class PermessoModel extends BaseModel
{
    protected $table            = 'permessi';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['chiave', 'descrizione'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'chiave'      => 'required|min_length[3]|max_length[100]|regex_match[/^[a-z0-9_.]+$/]|is_unique[permessi.chiave,id,{id}]',
        'descrizione' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'chiave' => [
            'is_unique'   => 'Esiste gia un permesso con questa chiave.',
            'regex_match' => 'Usa solo lettere minuscole, numeri, punto e underscore (es. utenti.view).',
        ],
    ];

    protected $skipValidation = false;

    public function inUso(int $permessoId): bool
    {
        return $this->db->table('ruolo_permesso')->where('permesso_id', $permessoId)->countAllResults() > 0;
    }
}
