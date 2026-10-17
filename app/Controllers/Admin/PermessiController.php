<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PermessoModel;

class PermessiController extends BaseController
{
    protected PermessoModel $model;

    public function __construct()
    {
        $this->model = new PermessoModel();
    }

    public function index()
    {
        return view('admin/permessi/index', ['title' => 'Permessi']);
    }

    public function datatable()
    {
        $request = $this->request;

        $sortableColumns = ['id', 'chiave', 'descrizione'];

        $draw        = (int) $request->getPost('draw');
        $start       = (int) $request->getPost('start');
        $length      = (int) $request->getPost('length');
        $searchValue = $request->getPost('search')['value'] ?? '';

        $orderCol = (int) ($request->getPost('order')[0]['column'] ?? 0);
        $orderDir = strtolower($request->getPost('order')[0]['dir'] ?? 'asc');
        $orderDir = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'asc';
        $orderColumnName = $sortableColumns[$orderCol] ?? $sortableColumns[0];

        $recordsTotal = $this->model->countAllResults(false);

        $builder = $this->model->conCreatoreModificatore();
        if (!empty($searchValue)) {
            $builder->groupStart()
                ->like('chiave', $searchValue)
                ->orLike('descrizione', $searchValue)
                ->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        $rows = $builder->orderBy($orderColumnName, $orderDir)
            ->get($length, $start)
            ->getResultArray();

        $puoModificare = $this->haPermesso('permessi.edit');
        $puoEliminare  = $this->haPermesso('permessi.delete');

        foreach ($rows as &$row) {
            $row['azioni'] = view('admin/permessi/_azioni', [
                'permesso'      => $row,
                'puoModificare' => $puoModificare,
                'puoEliminare'  => $puoEliminare && !$this->model->inUso((int) $row['id']),
            ], ['debug' => false]);
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }

    public function form(?int $id = null)
    {
        $permesso = null;

        if ($id !== null) {
            $permesso = $this->model->find($id);
            if (!$permesso) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Permesso non trovato.']);
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'html'    => view('admin/permessi/_form', ['permesso' => $permesso], ['debug' => false]),
        ]);
    }

    public function store()
    {
        if (!$this->validate($this->model->validationRules)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->validator->getErrors()]);
        }

        // La validazione e' gia' stata eseguita sopra: evita che il Model la rilanci
        // internamente con le regole grezze (query is_unique duplicata e inutile).
        $ok = $this->model->skipValidation(true)->insert([
            'chiave'      => $this->request->getPost('chiave'),
            'descrizione' => $this->request->getPost('descrizione'),
        ]);

        if ($ok === false) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->model->errors()]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Permesso creato con successo.']);
    }

    public function update(int $id)
    {
        if (!$this->model->find($id)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Permesso non trovato.']);
        }

        $rules = $this->model->validationRules;
        $rules['chiave'] = str_replace('{id}', (string) $id, $rules['chiave']);

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->validator->getErrors()]);
        }

        // skipValidation: la regola is_unique e' gia' stata validata sopra CON l'esclusione
        // dell'id corrente (vedi commento analogo in UtentiController::update()).
        $ok = $this->model->skipValidation(true)->update($id, [
            'chiave'      => $this->request->getPost('chiave'),
            'descrizione' => $this->request->getPost('descrizione'),
        ]);

        if ($ok === false) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->model->errors()]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Permesso aggiornato con successo.']);
    }

    public function delete(int $id)
    {
        if (!$this->model->find($id)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Permesso non trovato.']);
        }

        if ($this->model->inUso($id)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Impossibile eliminare: il permesso e assegnato ad almeno un ruolo.']);
        }

        $this->model->delete($id);

        return $this->response->setJSON(['success' => true, 'message' => 'Permesso eliminato.']);
    }

    private function haPermesso(string $chiave): bool
    {
        return in_array($chiave, session()->get('permessi') ?? [], true);
    }
}
