<?php

namespace App\Models;

class CasoTestModel extends BaseModel
{
    public const PRIORITA = ['alta', 'media', 'bassa'];

    protected $table            = 'casi_test';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    // stima_minuti non arriva dal form: e' la somma delle stime dei passaggi, ricalcolata
    // da sincronizzaPassaggi().
    protected $allowedFields = ['suite_id', 'titolo', 'precondizioni', 'priorita', 'stima_minuti'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'suite_id'      => 'required|is_natural_no_zero|is_not_unique[suite.id]',
        'titolo'        => 'required|min_length[2]|max_length[150]',
        'precondizioni' => 'permit_empty|max_length[2000]',
        'priorita'      => 'required|in_list[alta,media,bassa]',
    ];

    protected $skipValidation = false;

    /**
     * @return list<array<string, mixed>>
     */
    public function getPassaggi(int $casoId): array
    {
        return $this->db->table('passaggi')->where('caso_id', $casoId)->orderBy('ordine')->get()->getResultArray();
    }

    /**
     * Allinea i passaggi del caso a quelli inviati dal form. I passaggi con id sono
     * aggiornati, quelli senza sono creati, quelli non piu' presenti sono eliminati.
     * Un passaggio gia' eseguito in un piano non si elimina mai (storico): il controllo
     * avviene in anticipo con passaggiNonEliminabili().
     *
     * @param list<array{id?: int|string|null, azione: string, risultato_atteso?: string|null, stima_minuti: int|string}> $righe
     */
    public function sincronizzaPassaggi(int $casoId, array $righe): void
    {
        $passaggi = new PassaggioModel();
        $esistenti = array_column($this->getPassaggi($casoId), null, 'id');
        $mantenuti = [];

        $this->db->transStart();

        foreach (array_values($righe) as $indice => $riga) {
            $dati = [
                'caso_id'          => $casoId,
                'ordine'           => $indice + 1,
                'azione'           => trim((string) $riga['azione']),
                'risultato_atteso' => trim((string) ($riga['risultato_atteso'] ?? '')) ?: null,
                'stima_minuti'     => (int) $riga['stima_minuti'],
            ];
            $id = (int) ($riga['id'] ?? 0);

            if ($id > 0 && isset($esistenti[$id])) {
                $passaggi->update($id, $dati);
                $mantenuti[] = $id;
            } else {
                $passaggi->insert($dati);
            }
        }

        foreach (array_diff(array_keys($esistenti), $mantenuti) as $daEliminare) {
            $passaggi->delete($daEliminare);
        }

        // Update diretto sul builder: non deve toccare updated_by (qui cambia solo il totale).
        $this->db->table('casi_test')->where('id', $casoId)->update([
            'stima_minuti' => array_sum(array_map(static fn (array $riga) => (int) $riga['stima_minuti'], $righe)),
        ]);

        $this->db->transComplete();
    }

    /**
     * Passaggi del caso che il form non invia piu' ma che hanno gia' esecuzioni.
     *
     * @param list<array{id?: int|string|null}> $righe
     *
     * @return list<int>
     */
    public function passaggiNonEliminabili(int $casoId, array $righe): array
    {
        $inviati = array_map(static fn (array $riga) => (int) ($riga['id'] ?? 0), $righe);
        $passaggi = new PassaggioModel();
        $bloccati = [];

        foreach ($this->getPassaggi($casoId) as $passaggio) {
            if (!in_array((int) $passaggio['id'], $inviati, true) && $passaggi->haEsecuzioni((int) $passaggio['id'])) {
                $bloccati[] = (int) $passaggio['ordine'];
            }
        }

        return $bloccati;
    }

    public function utilizziInPiani(int $casoId): int
    {
        return $this->db->table('piano_casi')->where('caso_id', $casoId)->countAllResults();
    }
}
