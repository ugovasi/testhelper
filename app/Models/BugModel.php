<?php

namespace App\Models;

class BugModel extends BaseModel
{
    public const STATO_APERTO         = 'aperto';
    public const STATO_IN_LAVORAZIONE = 'in_lavorazione';
    public const STATO_RISOLTO        = 'risolto';
    public const STATO_CHIUSO         = 'chiuso';
    public const STATO_RIAPERTO       = 'riaperto';

    public const GRAVITA = ['bassa', 'media', 'alta', 'critica'];

    /** Stati in cui il bug aspetta ancora una correzione dello sviluppatore. */
    public const STATI_APERTI = [self::STATO_APERTO, self::STATO_IN_LAVORAZIONE, self::STATO_RIAPERTO];

    /**
     * Passaggi di stato che lo sviluppatore puo' compiere a mano. "chiuso" e
     * "riaperto" non sono qui: li imposta solo la riverifica del tester.
     */
    public const TRANSIZIONI_SVILUPPATORE = [
        self::STATO_APERTO         => [self::STATO_IN_LAVORAZIONE, self::STATO_RISOLTO],
        self::STATO_RIAPERTO       => [self::STATO_IN_LAVORAZIONE, self::STATO_RISOLTO],
        self::STATO_IN_LAVORAZIONE => [self::STATO_RISOLTO],
    ];

    protected $table            = 'bug';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'titolo', 'descrizione', 'gravita', 'stato', 'esecuzione_origine_id', 'assegnato_a',
        'note_risoluzione', 'risolto_il', 'chiuso_il',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'titolo'      => 'required|min_length[3]|max_length[150]',
        'descrizione' => 'permit_empty|max_length[5000]',
        'gravita'     => 'required|in_list[bassa,media,alta,critica]',
        'assegnato_a' => 'permit_empty|is_natural_no_zero|is_not_unique[utenti.id]',
    ];

    protected $skipValidation = false;

    /**
     * Bug con il contesto completo (prodotto, versione, piano, caso, passaggio) risolto
     * dall'esecuzione che l'ha scoperto.
     */
    public function conContesto()
    {
        return $this->conCreatoreModificatore()
            ->select('e.piano_caso_id, e.passaggio_id, e.note as note_esecuzione, p.ordine as passaggio_ordine, p.azione as passaggio_azione, '
                . 'p.risultato_atteso as passaggio_atteso, c.titolo as caso_titolo, pt.id as piano_id, pt.nome as piano_nome, '
                . 'v.nome as versione_nome, pr.nome as prodotto_nome, sv.nome as assegnato_nome')
            ->join('esecuzioni_passaggio e', 'e.id = bug.esecuzione_origine_id')
            ->join('passaggi p', 'p.id = e.passaggio_id')
            ->join('piano_casi pc', 'pc.id = e.piano_caso_id')
            ->join('casi_test c', 'c.id = pc.caso_id')
            ->join('piani_test pt', 'pt.id = pc.piano_id')
            ->join('versioni v', 'v.id = pt.versione_id')
            ->join('prodotti pr', 'pr.id = v.prodotto_id')
            ->join('utenti sv', 'sv.id = bug.assegnato_a', 'left');
    }
}
