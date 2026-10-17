<?php

namespace App\Models;

class UtenteModel extends BaseModel
{
    protected $table            = 'utenti';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    // Eliminare un utente lo disattiva solo logicamente (deleted_at valorizzato):
    // delete() non rimuove piu' la riga, find()/findAll()/first() la escludono
    // automaticamente. Vedi UtentiController::delete(), invariato: e' gia' una
    // semplice chiamata a $this->model->delete($id).
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'deleted_at';

    // IMPORTANTE: la password in chiaro non e' mai un campo assegnabile in massa.
    // Viene ricevuta separatamente dal controller, validata e trasformata in
    // password_hash prima di essere passata a insert()/update().
    protected $allowedFields = ['nome', 'email', 'password_hash', 'ruolo_id', 'attivo', 'ultimo_accesso'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Regole base (senza password: gestita a parte dal controller perche' e'
    // obbligatoria in creazione ma opzionale in modifica).
    protected $validationRules = [
        'nome'     => 'required|min_length[2]|max_length[100]',
        'email'    => 'required|valid_email|is_unique[utenti.email,id,{id}]',
        'ruolo_id' => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'email' => [
            'is_unique' => 'Questo indirizzo email e gia registrato.',
        ],
    ];

    protected $skipValidation = false;

    /**
     * Elenco utenti con il nome del ruolo associato (evita N+1 nelle liste).
     *
     * Filtro esplicito sui cancellati: questo metodo restituisce un builder
     * "grezzo" che chi lo chiama (es. UtentiController::datatable()) continua a
     * comporre con where()/orderBy()/get() propri, senza passare da find()/
     * findAll(), quindi il filtro automatico di $useSoftDeletes non si
     * applicherebbe da solo.
     */
    public function conRuolo()
    {
        $builder = $this->select('utenti.*, ruoli.nome as ruolo_nome')
            ->join('ruoli', 'ruoli.id = utenti.ruolo_id', 'left');

        if ($this->tempUseSoftDeletes) {
            $builder->where('utenti.' . $this->deletedField, null);
        }

        return $builder;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }
}
