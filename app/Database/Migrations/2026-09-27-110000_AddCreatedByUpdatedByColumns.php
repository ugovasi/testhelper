<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Aggiunge created_by/updated_by (id dell'utente che ha compiuto l'operazione,
 * accanto ai gia' esistenti created_at/updated_at) a ruoli, permessi e utenti.
 *
 * Niente vincolo FK verso utenti.id: su SQLite, CI4 applica una foreign key
 * aggiunta con ALTER TABLE ricreando l'intera tabella, e in questo processo
 * perde silenziosamente la clausola ON DELETE SET NULL delle FK gia' esistenti
 * (verificato empiricamente su questo progetto). Il riferimento resta quindi
 * solo applicativo, popolato automaticamente da App\Models\BaseModel.
 */
class AddCreatedByUpdatedByColumns extends Migration
{
    private const TABELLE = ['ruoli', 'permessi', 'utenti'];

    public function up()
    {
        foreach (self::TABELLE as $tabella) {
            $this->forge->addColumn($tabella, [
                'created_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'updated_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
            ]);
        }
    }

    public function down()
    {
        foreach (self::TABELLE as $tabella) {
            $this->forge->dropColumn($tabella, ['created_by', 'updated_by']);
        }
    }
}
