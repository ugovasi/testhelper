<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\PianoAvanzamento;
use App\Models\BugModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $permessi = session()->get('permessi') ?? [];
        $db       = db_connect();

        $piani = [];
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
        }

        $bugAperti = null;
        if (in_array('bug.view', $permessi, true)) {
            $bugAperti = $db->table('bug')->whereIn('stato', BugModel::STATI_APERTI)->countAllResults();
        }

        return view('admin/dashboard', [
            'title'     => 'Dashboard',
            'piani'     => $piani,
            'bugAperti' => $bugAperti,
        ]);
    }
}
