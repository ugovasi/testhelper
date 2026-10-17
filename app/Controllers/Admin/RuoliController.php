<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PermessoModel;
use App\Models\RuoloModel;

/**
 * CRUD Ruoli, inclusa l'assegnazione della matrice di permessi tramite
 * checkbox nello stesso form modale di creazione/modifica.
 */
class RuoliController extends BaseController
{
    protected RuoloModel $model;

    public function __construct()
    {
        $this->model = new RuoloModel();
    }

    public function index()
    {
        return view('admin/ruoli/index', ['title' => 'Ruoli']);
    }

    public function datatable()
    {
        $request = $this->request;

        $sortableColumns = ['id', 'nome', 'descrizione'];

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
                ->like('nome', $searchValue)
                ->orLike('descrizione', $searchValue)
                ->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        $rows = $builder->orderBy($orderColumnName, $orderDir)
            ->get($length, $start)
            ->getResultArray();

        $puoModificare = $this->haPermesso('ruoli.edit');
        $puoEliminare  = $this->haPermesso('ruoli.delete');

        foreach ($rows as &$row) {
            $row['azioni'] = view('admin/ruoli/_azioni', [
                'ruolo'         => $row,
                'puoModificare' => $puoModificare,
                // Un ruolo assegnato ad almeno un utente non e' eliminabile.
                'puoEliminare'  => $puoEliminare && $this->model->utentiAssegnati((int) $row['id']) === 0,
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
        $ruolo             = null;
        $permessiAssegnati = [];

        if ($id !== null) {
            $ruolo = $this->model->find($id);
            if (!$ruolo) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Ruolo non trovato.']);
            }
            $permessiAssegnati = $this->model->getPermessi($id);
        }

        $permessi = (new PermessoModel())->orderBy('chiave')->findAll();

        return $this->response->setJSON([
            'success' => true,
            'html'    => view('admin/ruoli/_form', [
                'ruolo'             => $ruolo,
                'permessi'          => $permessi,
                'permessiAssegnati' => $permessiAssegnati,
            ], ['debug' => false]),
        ]);
    }

    public function store()
    {
        if (!$this->validate($this->model->validationRules)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->validator->getErrors()]);
        }

        // La validazione e' gia' stata eseguita sopra: evita che il Model la rilanci
        // internamente con le regole grezze (query is_unique duplicata e inutile).
        $ruoloId = $this->model->skipValidation(true)->insert([
            'nome'        => $this->request->getPost('nome'),
            'descrizione' => $this->request->getPost('descrizione'),
        ]);

        if ($ruoloId === false) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->model->errors()]);
        }

        $permessiIds = array_map('intval', (array) $this->request->getPost('permessi'));
        $this->model->sincronizzaPermessi($ruoloId, $permessiIds);

        return $this->response->setJSON(['success' => true, 'message' => 'Ruolo creato con successo.']);
    }

    public function update(int $id)
    {
        if (!$this->model->find($id)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Ruolo non trovato.']);
        }

        $rules = $this->model->validationRules;
        $rules['nome'] = str_replace('{id}', (string) $id, $rules['nome']);

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->validator->getErrors()]);
        }

        // skipValidation: la regola is_unique e' gia' stata validata sopra CON l'esclusione
        // dell'id corrente (vedi commento analogo in UtentiController::update()).
        $ok = $this->model->skipValidation(true)->update($id, [
            'nome'        => $this->request->getPost('nome'),
            'descrizione' => $this->request->getPost('descrizione'),
        ]);

        if ($ok === false) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->model->errors()]);
        }

        $permessiIds = array_map('intval', (array) $this->request->getPost('permessi'));
        $this->model->sincronizzaPermessi($id, $permessiIds);

        // Se un utente in sessione appartiene a questo ruolo, aggiorna subito i suoi permessi.
        if ($id === (int) session()->get('ruolo_id')) {
            session()->set('permessi', $this->model->getPermessi($id));
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Ruolo aggiornato con successo.']);
    }

    public function delete(int $id)
    {
        if (!$this->model->find($id)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Ruolo non trovato.']);
        }

        if ($this->model->utentiAssegnati($id) > 0) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Impossibile eliminare: il ruolo e assegnato ad almeno un utente.']);
        }

        $this->model->delete($id);

        return $this->response->setJSON(['success' => true, 'message' => 'Ruolo eliminato.']);
    }

    private function haPermesso(string $chiave): bool
    {
        return in_array($chiave, session()->get('permessi') ?? [], true);
    }
}
