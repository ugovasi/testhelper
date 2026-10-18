<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Metadati degli allegati dei bug (screenshot, log, ...). I file stanno fuori da public/
 * (writable/uploads/bug/) con nome casuale e si leggono solo tramite BugController.
 */
class CreateBugAllegatiTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'bug_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nome_originale'  => ['type' => 'VARCHAR', 'constraint' => 150],
            // Nome casuale sul disco: non deriva mai da quello scelto dall'utente.
            'nome_file'       => ['type' => 'VARCHAR', 'constraint' => 80],
            'mime'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'dimensione'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'caricato_da'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('bug_id');
        $this->forge->addForeignKey('bug_id', 'bug', 'id', '', 'CASCADE');
        $this->forge->createTable('bug_allegati');
    }

    public function down()
    {
        $this->forge->dropTable('bug_allegati');
    }
}
