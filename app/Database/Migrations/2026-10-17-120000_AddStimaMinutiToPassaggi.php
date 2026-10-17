<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La stima dei tempi si da' per passaggio: la stima del caso (casi_test.stima_minuti)
 * diventa la somma dei suoi passaggi e viene ricalcolata a ogni salvataggio del caso.
 */
class AddStimaMinutiToPassaggi extends Migration
{
    public function up()
    {
        $this->forge->addColumn('passaggi', [
            'stima_minuti' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0, 'after' => 'risultato_atteso'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('passaggi', 'stima_minuti');
    }
}
