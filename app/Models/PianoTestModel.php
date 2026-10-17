<?php

namespace App\Models;

class PianoTestModel extends BaseModel
{
    public const STATO_BOZZA    = 'bozza';
    public const STATO_IN_CORSO = 'in_corso';
    public const STATO_CHIUSO   = 'chiuso';

    protected $table            = 'piani_test';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    // 'stato' e 'data_fine_effettiva' sono modificati solo dal flusso di esecuzione
    // (EsecuzioneService), non da un form: ma restano assegnabili al model.
    protected $allowedFields = [
        'versione_id', 'nome', 'assegnatario_id', 'data_inizio', 'data_fine_prevista',
        'data_fine_effettiva', 'quota_riverifica', 'stato',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'versione_id'        => 'required|is_natural_no_zero|is_not_unique[versioni.id]',
        'nome'               => 'required|min_length[2]|max_length[150]',
        'assegnatario_id'    => 'permit_empty|is_natural_no_zero|is_not_unique[utenti.id]',
        'data_inizio'        => 'required|valid_date[Y-m-d]',
        'data_fine_prevista' => 'required|valid_date[Y-m-d]',
        'quota_riverifica'   => 'required|is_natural|less_than_equal_to[100]',
    ];

    protected $skipValidation = false;

    /**
     * @return list<array<string, mixed>>
     */
    public function getPianoCasi(int $pianoId): array
    {
        return $this->db->table('piano_casi pc')
            ->select('pc.*, c.titolo, c.priorita, s.nome as suite_nome')
            ->join('casi_test c', 'c.id = pc.caso_id')
            ->join('suite s', 's.id = c.suite_id')
            ->where('pc.piano_id', $pianoId)
            ->orderBy('pc.ordine')
            ->get()
            ->getResultArray();
    }

    /**
     * Allinea i casi del piano all'elenco inviato dal form: aggiunge i nuovi (copiando
     * la stima dalla libreria), rimuove quelli deselezionati. I casi gia' eseguiti
     * non si rimuovono (controllo anticipato con casiNonRimovibili()).
     *
     * @param int[] $casoIds
     */
    public function sincronizzaCasi(int $pianoId, array $casoIds): void
    {
        $esistenti = array_column($this->getPianoCasi($pianoId), null, 'caso_id');
        $casoIds   = array_values(array_unique(array_map('intval', $casoIds)));
        $ora       = date('Y-m-d H:i:s');

        $this->db->transStart();

        foreach ($casoIds as $indice => $casoId) {
            if (isset($esistenti[$casoId])) {
                $this->db->table('piano_casi')->where('id', $esistenti[$casoId]['id'])->update(['ordine' => $indice + 1, 'updated_at' => $ora]);

                continue;
            }

            $caso = $this->db->table('casi_test')->select('stima_minuti')->where('id', $casoId)->get()->getRowArray();
            if ($caso === null) {
                continue;
            }

            $this->db->table('piano_casi')->insert([
                'piano_id'     => $pianoId,
                'caso_id'      => $casoId,
                'ordine'       => $indice + 1,
                'stima_minuti' => (int) $caso['stima_minuti'],
                'created_at'   => $ora,
                'updated_at'   => $ora,
            ]);
        }

        foreach ($esistenti as $casoId => $pianoCaso) {
            if (!in_array((int) $casoId, $casoIds, true)) {
                $this->db->table('piano_casi')->where('id', $pianoCaso['id'])->delete();
            }
        }

        $this->db->transComplete();
    }

    /**
     * Titoli dei casi che il form deseleziona ma che hanno gia' esecuzioni registrate.
     *
     * @param int[] $casoIds
     *
     * @return list<string>
     */
    public function casiNonRimovibili(int $pianoId, array $casoIds): array
    {
        $casoIds  = array_map('intval', $casoIds);
        $bloccati = [];

        foreach ($this->getPianoCasi($pianoId) as $pianoCaso) {
            if (in_array((int) $pianoCaso['caso_id'], $casoIds, true)) {
                continue;
            }

            $haEsecuzioni = $this->db->table('esecuzioni_passaggio')
                ->where('piano_caso_id', $pianoCaso['id'])
                ->countAllResults() > 0;

            if ($haEsecuzioni) {
                $bloccati[] = $pianoCaso['titolo'];
            }
        }

        return $bloccati;
    }

    public function haEsecuzioni(int $pianoId): bool
    {
        return $this->db->table('esecuzioni_passaggio e')
            ->join('piano_casi pc', 'pc.id = e.piano_caso_id')
            ->where('pc.piano_id', $pianoId)
            ->countAllResults() > 0;
    }
}
