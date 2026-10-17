<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLogAttivitaTable extends Migration
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
            'utente_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            // Nome/email dell'utente al momento dell'azione: restano leggibili anche
            // se l'utente viene poi rinominato o eliminato (utente_id -> SET NULL).
            'utente_nome' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'utente_email' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'azione' => [
                // create | update | delete | login | logout
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'modulo' => [
                // utenti | ruoli | permessi | auth
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'entita_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'descrizione' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'esito' => [
                // 1 = successo, 0 = fallito (es. login con credenziali errate)
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('utente_id');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('utente_id', 'utenti', 'id', '', 'SET NULL');
        $this->forge->createTable('log_attivita');
    }

    public function down()
    {
        $this->forge->dropTable('log_attivita');
    }
}
