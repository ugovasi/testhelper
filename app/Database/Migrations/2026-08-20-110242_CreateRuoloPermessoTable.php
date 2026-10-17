<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRuoloPermessoTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'ruolo_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'permesso_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
        ]);

        $this->forge->addPrimaryKey(['ruolo_id', 'permesso_id']);
        $this->forge->addForeignKey('ruolo_id', 'ruoli', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('permesso_id', 'permessi', 'id', '', 'CASCADE');
        $this->forge->createTable('ruolo_permesso');
    }

    public function down()
    {
        $this->forge->dropTable('ruolo_permesso');
    }
}
