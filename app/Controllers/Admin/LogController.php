<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LogAttivitaModel;

/**
 * Consultazione del registro attivita' (log_attivita). Sola lettura: le righe sono
 * scritte automaticamente da App\Filters\ActivityLogFilter, mai da questo controller.
 */
class LogController extends BaseController
{
    protected LogAttivitaModel $model;

    public function __construct()
    {
        $this->model = new LogAttivitaModel();
    }

    public function index()
    {
        return view('admin/log/index', ['title' => 'Registro attivita']);
    }

    public function datatable()
    {
        $request = $this->request;

        $sortableColumns = ['id', 'created_at', 'utente_nome', 'azione', 'modulo', 'esito'];

        $draw        = (int) $request->getPost('draw');
        $start       = (int) $request->getPost('start');
        $length      = (int) $request->getPost('length');
        $searchValue = $request->getPost('search')['value'] ?? '';

        $orderCol = (int) ($request->getPost('order')[0]['column'] ?? 1);
        $orderDir = strtolower($request->getPost('order')[0]['dir'] ?? 'desc');
        $orderDir = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'desc';
        $orderColumnName = $sortableColumns[$orderCol] ?? 'created_at';

        $recordsTotal = $this->model->countAllResults(false);

        $builder = $this->model->builder();
        if (!empty($searchValue)) {
            $builder->groupStart()
                ->like('utente_nome', $searchValue)
                ->orLike('utente_email', $searchValue)
                ->orLike('azione', $searchValue)
                ->orLike('modulo', $searchValue)
                ->orLike('descrizione', $searchValue)
                ->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        $rows = $builder->orderBy($orderColumnName, $orderDir)
            ->get($length, $start)
            ->getResultArray();

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }
}
