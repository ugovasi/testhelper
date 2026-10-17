<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Permessi e ruoli della gestione piani di test (Test manager, Tester, Sviluppatore).
 *
 * Idempotente: puo' essere eseguito piu' volte, e anche su un database gia' popolato da
 * RbacSeeder, senza duplicare nulla. L'Amministratore riceve sempre tutti i permessi.
 *
 * Esegui con: php spark db:seed GestioneTestSeeder
 */
class GestioneTestSeeder extends Seeder
{
    private const PERMESSI = [
        'prodotti.view'     => 'Visualizza prodotti e versioni',
        'prodotti.create'   => 'Crea prodotti e versioni',
        'prodotti.edit'     => 'Modifica prodotti e versioni',
        'prodotti.delete'   => 'Elimina prodotti e versioni',
        'casi.view'         => 'Visualizza suite e casi di test',
        'casi.create'       => 'Crea suite e casi di test',
        'casi.edit'         => 'Modifica suite e casi di test',
        'casi.delete'       => 'Elimina suite e casi di test',
        'piani.view'        => 'Visualizza i piani di test e il cruscotto',
        'piani.create'      => 'Crea piani di test',
        'piani.edit'        => 'Modifica piani di test',
        'piani.delete'      => 'Elimina piani di test',
        'esecuzioni.view'   => 'Visualizza le esecuzioni dei test',
        'esecuzioni.create' => 'Registra esiti, tempi e chiude i piani',
        'bug.view'          => 'Visualizza i bug',
        'bug.update'        => 'Porta i bug in lavorazione o risolti',
    ];

    private const RUOLI = [
        'Test manager' => [
            'descrizione' => 'Gestisce prodotti, libreria dei casi e piani di test; controlla l\'avanzamento',
            'permessi'    => [
                'prodotti.view', 'prodotti.create', 'prodotti.edit', 'prodotti.delete',
                'casi.view', 'casi.create', 'casi.edit', 'casi.delete',
                'piani.view', 'piani.create', 'piani.edit', 'piani.delete',
                'esecuzioni.view', 'bug.view',
            ],
        ],
        'Tester' => [
            'descrizione' => 'Esegue i passaggi dei piani, apre i bug e riverifica le correzioni',
            'permessi'    => ['prodotti.view', 'casi.view', 'piani.view', 'esecuzioni.view', 'esecuzioni.create', 'bug.view'],
        ],
        'Sviluppatore' => [
            'descrizione' => 'Prende in carico e risolve i bug assegnati',
            'permessi'    => ['piani.view', 'esecuzioni.view', 'bug.view', 'bug.update'],
        ],
    ];

    public function run()
    {
        $ora = date('Y-m-d H:i:s');

        foreach (self::PERMESSI as $chiave => $descrizione) {
            $esiste = $this->db->table('permessi')->where('chiave', $chiave)->countAllResults() > 0;
            if (!$esiste) {
                $this->db->table('permessi')->insert(['chiave' => $chiave, 'descrizione' => $descrizione, 'created_at' => $ora, 'updated_at' => $ora]);
            }
        }

        $permessoIds = array_column($this->db->table('permessi')->get()->getResultArray(), 'id', 'chiave');

        foreach (self::RUOLI as $nome => $definizione) {
            $ruolo = $this->db->table('ruoli')->where('nome', $nome)->get()->getRowArray();
            if ($ruolo === null) {
                $this->db->table('ruoli')->insert(['nome' => $nome, 'descrizione' => $definizione['descrizione'], 'created_at' => $ora, 'updated_at' => $ora]);
                $ruoloId = (int) $this->db->insertID();
            } else {
                $ruoloId = (int) $ruolo['id'];
            }

            $this->assegna($ruoloId, $definizione['permessi'], $permessoIds);
        }

        $amministratore = $this->db->table('ruoli')->where('nome', 'Amministratore')->get()->getRowArray();
        if ($amministratore !== null) {
            $this->assegna((int) $amministratore['id'], array_keys(self::PERMESSI), $permessoIds);
        }
    }

    /**
     * @param string[]           $chiavi
     * @param array<string, int> $permessoIds
     */
    private function assegna(int $ruoloId, array $chiavi, array $permessoIds): void
    {
        foreach ($chiavi as $chiave) {
            $presente = $this->db->table('ruolo_permesso')
                ->where('ruolo_id', $ruoloId)
                ->where('permesso_id', $permessoIds[$chiave])
                ->countAllResults() > 0;

            if (!$presente) {
                $this->db->table('ruolo_permesso')->insert(['ruolo_id' => $ruoloId, 'permesso_id' => (int) $permessoIds[$chiave]]);
            }
        }
    }
}
