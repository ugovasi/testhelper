<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Abilita la cancellazione logica degli utenti: 'elimina' da UI valorizza
 * questa colonna invece di rimuovere la riga (vedi UtenteModel::$useSoftDeletes).
 */
class AddSoftDeleteToUtenti extends Migration
{
    public function up()
    {
        $this->forge->addColumn('utenti', [
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('utenti', 'deleted_at');
    }
}
