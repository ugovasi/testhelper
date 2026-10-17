<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePermessiTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'chiave' => [
                // slug univoco usato nei controller/filtri, es. "utenti.view"
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'descrizione' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('chiave');
        $this->forge->createTable('permessi');
    }

    public function down()
    {
        $this->forge->dropTable('permessi');
    }
}
