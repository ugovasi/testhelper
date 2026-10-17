<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Comune a tutti i model con tracciamento "chi" (created_by/updated_by): valorizza
 * queste colonne con l'utente in sessione, esattamente come CodeIgniter gestisce da
 * solo il "quando" (created_at/updated_at) tramite $useTimestamps. Non vanno mai
 * aggiunte a $allowedFields: sono campi di sistema, non assegnabili da un form.
 */
abstract class BaseModel extends Model
{
    protected $beforeInsert = ['impostaCreatoDa'];
    protected $beforeUpdate = ['impostaModificatoDa'];

    /**
     * @param array{data: array<string, mixed>} $dati
     *
     * @return array{data: array<string, mixed>}
     */
    protected function impostaCreatoDa(array $dati): array
    {
        $utenteId = session()->get('user_id');

        $dati['data']['created_by'] ??= $utenteId;
        $dati['data']['updated_by'] ??= $utenteId;

        return $dati;
    }

    /**
     * @param array{data: array<string, mixed>} $dati
     *
     * @return array{data: array<string, mixed>}
     */
    protected function impostaModificatoDa(array $dati): array
    {
        $dati['data']['updated_by'] = session()->get('user_id');

        return $dati;
    }

    /**
     * Risolve created_by/updated_by nei nomi di chi ha creato/modificato la riga,
     * con un doppio self-join su utenti (creatore/modificatore). Usato dalle
     * datatable dei moduli admin per l'icona "info" al posto della colonna
     * "Creato il" (vedi public/assets/js/admin-crud.js: infoPopoverHtml()).
     *
     * Come App\Models\UtenteModel::conRuolo(), restituisce un builder "grezzo"
     * pensato per essere ulteriormente composto con where()/orderBy()/get() dal
     * chiamante, non per passare da find()/findAll().
     */
    public function conCreatoreModificatore()
    {
        $builder = $this->select($this->table . '.*, creatore.nome as creato_da_nome, modificatore.nome as modificato_da_nome')
            ->join('utenti creatore', 'creatore.id = ' . $this->table . '.created_by', 'left')
            ->join('utenti modificatore', 'modificatore.id = ' . $this->table . '.updated_by', 'left');

        if ($this->tempUseSoftDeletes) {
            $builder->where($this->table . '.' . $this->deletedField, null);
        }

        return $builder;
    }
}
