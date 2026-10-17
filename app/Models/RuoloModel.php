<?php

namespace App\Models;

class RuoloModel extends BaseModel
{
    protected $table            = 'ruoli';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['nome', 'descrizione'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'nome'        => 'required|min_length[2]|max_length[50]|is_unique[ruoli.nome,id,{id}]',
        'descrizione' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'nome' => [
            'is_unique' => 'Esiste gia un ruolo con questo nome.',
        ],
    ];

    protected $skipValidation = false;

    /**
     * Chiavi dei permessi assegnati al ruolo (join su ruolo_permesso).
     */
    public function getPermessi(int $ruoloId): array
    {
        return array_column(
            $this->db->table('ruolo_permesso rp')
                ->select('p.chiave')
                ->join('permessi p', 'p.id = rp.permesso_id')
                ->where('rp.ruolo_id', $ruoloId)
                ->get()
                ->getResultArray(),
            'chiave'
        );
    }

    /**
     * Sostituisce l'intera matrice di permessi assegnati a un ruolo.
     *
     * @param int[] $permessoIds
     */
    public function sincronizzaPermessi(int $ruoloId, array $permessoIds): void
    {
        $this->db->transStart();

        $this->db->table('ruolo_permesso')->where('ruolo_id', $ruoloId)->delete();

        if (!empty($permessoIds)) {
            $righe = array_map(
                static fn ($permessoId) => ['ruolo_id' => $ruoloId, 'permesso_id' => (int) $permessoId],
                $permessoIds
            );
            $this->db->table('ruolo_permesso')->insertBatch($righe);
        }

        $this->db->transComplete();
    }

    public function utentiAssegnati(int $ruoloId): int
    {
        // Un utente cancellato (soft delete) non conta come "ancora assegnato":
        // altrimenti il ruolo resterebbe bloccato per sempre e non eliminabile.
        return $this->db->table('utenti')
            ->where('ruolo_id', $ruoloId)
            ->where('deleted_at', null)
            ->countAllResults();
    }
}
