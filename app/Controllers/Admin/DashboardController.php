<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\PianoAvanzamento;
use App\Libraries\StatisticheDashboard;

class DashboardController extends BaseController
{
    public function index()
    {
        $permessi = session()->get('permessi') ?? [];
        $db       = db_connect();

        $piani    = [];
        $statTest = null;
        if (in_array('piani.view', $permessi, true)) {
            $avanzamento = new PianoAvanzamento();
            $aperti = $db->table('piani_test p')
                ->select('p.*, v.nome as versione_nome, pr.nome as prodotto_nome')
                ->join('versioni v', 'v.id = p.versione_id')
                ->join('prodotti pr', 'pr.id = v.prodotto_id')
                ->where('p.stato !=', 'chiuso')
                ->orderBy('p.data_fine_prevista')
                ->get()->getResultArray();

            foreach ($aperti as $piano) {
                $piani[] = $piano + ['metriche' => $avanzamento->carica((int) $piano['id'])['metriche']];
            }

            $statTest = [
                'piani'      => StatisticheDashboard::test(),
                'passaggi'   => StatisticheDashboard::passaggi(array_column($piani, 'metriche')),
                'esecuzioni' => StatisticheDashboard::esecuzioniPerGiorno(),
            ];
        }

        $bugAperti = null;
        $statBug   = null;
        if (in_array('bug.view', $permessi, true)) {
            $statBug   = StatisticheDashboard::bug();
            $bugAperti = $statBug['aperti'];
        }

        return view('admin/dashboard', [
            'title'     => 'Dashboard',
            'piani'     => $piani,
            'bugAperti' => $bugAperti,
            'statTest'  => $statTest,
            'statBug'   => $statBug,
        ]);
    }
}
