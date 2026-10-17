<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Libreria riusabile dei test: prodotti -> versioni, suite -> casi di test -> passaggi.
 *
 * created_by/updated_by sono dichiarati qui (non in una migration separata) per le
 * tabelle gestite da modelli che estendono App\Models\BaseModel; come per le altre
 * tabelle, non hanno FK verso utenti (vedi AddCreatedByUpdatedByColumns).
 */
class CreateLibreriaTestTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nome'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'descrizione' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'attivo'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ...$this->campiAudit(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nome');
        $this->forge->createTable('prodotti');

        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'prodotto_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nome'                   => ['type' => 'VARCHAR', 'constraint' => 50],
            'data_rilascio_prevista' => ['type' => 'DATE', 'null' => true],
            ...$this->campiAudit(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('prodotto_id');
        $this->forge->addForeignKey('prodotto_id', 'prodotti', 'id', '', 'RESTRICT');
        $this->forge->createTable('versioni');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'prodotto_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nome'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'descrizione' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ...$this->campiAudit(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('prodotto_id');
        $this->forge->addForeignKey('prodotto_id', 'prodotti', 'id', '', 'RESTRICT');
        $this->forge->createTable('suite');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'suite_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'titolo'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'precondizioni' => ['type' => 'TEXT', 'null' => true],
            // alta | media | bassa
            'priorita'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'media'],
            'stima_minuti'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            ...$this->campiAudit(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('suite_id');
        $this->forge->addForeignKey('suite_id', 'suite', 'id', '', 'RESTRICT');
        $this->forge->createTable('casi_test');

        // Il passaggio e' l'unita' di esito: non estende BaseModel (nessun created_by).
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'caso_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'ordine'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'azione'            => ['type' => 'TEXT'],
            'risultato_atteso'  => ['type' => 'TEXT', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('caso_id');
        $this->forge->addForeignKey('caso_id', 'casi_test', 'id', '', 'CASCADE');
        $this->forge->createTable('passaggi');
    }

    public function down()
    {
        foreach (['passaggi', 'casi_test', 'suite', 'versioni', 'prodotti'] as $tabella) {
            $this->forge->dropTable($tabella);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function campiAudit(): array
    {
        return [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ];
    }
}
