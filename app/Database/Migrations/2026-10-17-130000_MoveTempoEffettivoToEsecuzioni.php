<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Il tempo effettivo si registra a ogni esecuzione di un passaggio (anche per ogni
 * riverifica), non piu' una volta sola per caso: il totale del caso e del piano si
 * ricava sommando i tentativi. La colonna per-caso diventa quindi superflua.
 */
class MoveTempoEffettivoToEsecuzioni extends Migration
{
    public function up()
    {
        $this->forge->addColumn('esecuzioni_passaggio', [
            'minuti_impiegati' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'note'],
        ]);

        $this->eliminaColonna('piano_casi', 'minuti_effettivi');
    }

    public function down()
    {
        $this->forge->addColumn('piano_casi', [
            'minuti_effettivi' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'stima_minuti'],
        ]);

        $this->eliminaColonna('esecuzioni_passaggio', 'minuti_impiegati');
    }

    /**
     * Su SQLite il dropColumn di CI4 ricrea l'intera tabella, e fallisce quando altre
     * tabelle hanno una FK verso di essa (qui entrambe ne hanno). SQLite >= 3.35 sa
     * eliminare una colonna con un semplice ALTER TABLE: lo usiamo direttamente.
     */
    private function eliminaColonna(string $tabella, string $colonna): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefixTable($tabella)) . ' DROP COLUMN ' . $this->db->escapeIdentifiers($colonna));

            return;
        }

        $this->forge->dropColumn($tabella, $colonna);
    }
}
