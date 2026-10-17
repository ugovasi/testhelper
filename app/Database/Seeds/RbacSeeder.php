<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Popola ruoli, permessi, la matrice ruolo->permesso e un utente amministratore
 * iniziale, cosi' l'applicazione e' subito utilizzabile dopo `php spark migrate`.
 *
 * Esegui con: php spark db:seed RbacSeeder
 */
class RbacSeeder extends Seeder
{
    public function run()
    {
        $moduli = ['utenti', 'ruoli', 'permessi'];
        $azioni = ['view', 'create', 'edit', 'delete'];

        $permessi = [];
        foreach ($moduli as $modulo) {
            foreach ($azioni as $azione) {
                $permessi[] = [
                    'chiave'      => "{$modulo}.{$azione}",
                    'descrizione' => ucfirst($azione) . ' ' . $modulo,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ];
            }
        }
        // Non segue il pattern modulo.azione: e' una sola permission di sola lettura
        // sul registro attivita', non un CRUD (le righe di log non si creano/modificano
        // da UI). Va nel loop di insertBatch cosi' l'Amministratore la riceve
        // automaticamente insieme a tutte le altre (vedi assegnazioni sotto).
        $permessi[] = [
            'chiave'      => 'log.view',
            'descrizione' => 'Visualizza il registro attivita',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $this->db->table('permessi')->insertBatch($permessi);

        $permessoIds = array_column($this->db->table('permessi')->get()->getResultArray(), 'id', 'chiave');

        $ruoli = [
            [
                'nome'        => 'Amministratore',
                'descrizione' => 'Accesso completo a tutte le funzionalita',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
            [
                'nome'        => 'Operatore',
                'descrizione' => 'Gestione utenti in sola lettura/creazione/modifica, nessun accesso a ruoli e permessi',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
            [
                'nome'        => 'Lettore',
                'descrizione' => 'Sola visualizzazione utenti',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
        ];
        $this->db->table('ruoli')->insertBatch($ruoli);

        $ruoloIds = array_column($this->db->table('ruoli')->get()->getResultArray(), 'id', 'nome');

        $assegnazioni = [];
        // Amministratore: tutti i permessi
        foreach ($permessoIds as $permessoId) {
            $assegnazioni[] = ['ruolo_id' => $ruoloIds['Amministratore'], 'permesso_id' => $permessoId];
        }
        // Operatore: view/create/edit su utenti
        foreach (['utenti.view', 'utenti.create', 'utenti.edit'] as $chiave) {
            $assegnazioni[] = ['ruolo_id' => $ruoloIds['Operatore'], 'permesso_id' => $permessoIds[$chiave]];
        }
        // Lettore: solo view su utenti
        $assegnazioni[] = ['ruolo_id' => $ruoloIds['Lettore'], 'permesso_id' => $permessoIds['utenti.view']];

        $this->db->table('ruolo_permesso')->insertBatch($assegnazioni);

        $this->db->table('utenti')->insert([
            'nome'           => 'Amministratore',
            'email'          => 'admin@example.com',
            'password_hash'  => password_hash('Admin123!', PASSWORD_DEFAULT),
            'ruolo_id'       => $ruoloIds['Amministratore'],
            'attivo'         => 1,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // Permessi e ruoli della gestione piani di test (Amministratore compreso).
        $this->call(GestioneTestSeeder::class);
    }
}
