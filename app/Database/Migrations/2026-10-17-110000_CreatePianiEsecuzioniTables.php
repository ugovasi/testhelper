<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Piani di test, casi inclusi nel piano, esecuzioni per passaggio e bug.
 *
 * Le esecuzioni non si sovrascrivono mai: ogni (re)esecuzione di un passaggio e' una
 * nuova riga con tentativo + 1, e l'esito corrente e' quello dell'ultimo tentativo.
 */
class CreatePianiEsecuzioniTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'versione_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nome'                 => ['type' => 'VARCHAR', 'constraint' => 150],
            'assegnatario_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'data_inizio'          => ['type' => 'DATE'],
            'data_fine_prevista'   => ['type' => 'DATE'],
            'data_fine_effettiva'  => ['type' => 'DATE', 'null' => true],
            // Percentuale di tempo extra per le riverifiche, sommata alla stima dei casi.
            'quota_riverifica'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 15],
            // bozza | in_corso | chiuso
            'stato'                => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'bozza'],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'created_by'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('versione_id');
        $this->forge->addForeignKey('versione_id', 'versioni', 'id', '', 'RESTRICT');
        $this->forge->createTable('piani_test');

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'piano_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'caso_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'ordine'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            // Copiata dal caso al momento dell'inserimento nel piano: cambiarla in
            // libreria non altera le stime di un piano gia' avviato.
            'stima_minuti'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'minuti_effettivi' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('piano_id');
        $this->forge->addUniqueKey(['piano_id', 'caso_id']);
        $this->forge->addForeignKey('piano_id', 'piani_test', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('caso_id', 'casi_test', 'id', '', 'RESTRICT');
        $this->forge->createTable('piano_casi');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'piano_caso_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'passaggio_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tentativo'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            // passato | fallito | bloccato | saltato
            'esito'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'note'          => ['type' => 'TEXT', 'null' => true],
            'eseguito_da'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'eseguito_il'   => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['piano_caso_id', 'passaggio_id']);
        $this->forge->addUniqueKey(['piano_caso_id', 'passaggio_id', 'tentativo']);
        $this->forge->addForeignKey('piano_caso_id', 'piano_casi', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('passaggio_id', 'passaggi', 'id', '', 'RESTRICT');
        $this->forge->createTable('esecuzioni_passaggio');

        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'titolo'                => ['type' => 'VARCHAR', 'constraint' => 150],
            'descrizione'           => ['type' => 'TEXT', 'null' => true],
            // bassa | media | alta | critica
            'gravita'               => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'media'],
            // aperto | in_lavorazione | risolto | chiuso | riaperto
            'stato'                 => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'aperto'],
            // Esecuzione che ha scoperto il bug: lega il difetto al passaggio da riverificare.
            'esecuzione_origine_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'assegnato_a'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'note_risoluzione'      => ['type' => 'TEXT', 'null' => true],
            'risolto_il'            => ['type' => 'DATETIME', 'null' => true],
            'chiuso_il'             => ['type' => 'DATETIME', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
            'created_by'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('esecuzione_origine_id');
        $this->forge->addKey('assegnato_a');
        $this->forge->addForeignKey('esecuzione_origine_id', 'esecuzioni_passaggio', 'id', '', 'CASCADE');
        $this->forge->createTable('bug');
    }

    public function down()
    {
        foreach (['bug', 'esecuzioni_passaggio', 'piano_casi', 'piani_test'] as $tabella) {
            $this->forge->dropTable($tabella);
        }
    }
}
