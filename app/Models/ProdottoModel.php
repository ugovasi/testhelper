<?php

namespace App\Models;

class ProdottoModel extends BaseModel
{
    protected $table            = 'prodotti';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['nome', 'descrizione', 'attivo'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'nome'        => 'required|min_length[2]|max_length[100]|is_unique[prodotti.nome,id,{id}]',
        'descrizione' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'nome' => [
            'is_unique' => 'Esiste gia un prodotto con questo nome.',
        ],
    ];

    protected $skipValidation = false;

    /**
     * Versioni e suite impediscono l'eliminazione del prodotto (FK RESTRICT).
     */
    public function elementiCollegati(int $prodottoId): int
    {
        return $this->db->table('versioni')->where('prodotto_id', $prodottoId)->countAllResults()
            + $this->db->table('suite')->where('prodotto_id', $prodottoId)->countAllResults();
    }
}
