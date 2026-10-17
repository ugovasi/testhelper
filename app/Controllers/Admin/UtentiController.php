<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\RuoloModel;
use App\Models\UtenteModel;

/**
 * CRUD Utenti — tutte le operazioni di creazione/modifica/eliminazione avvengono
 * via AJAX da form dentro modali Bootstrap (nessuna pagina di create/edit dedicata).
 */
class UtentiController extends BaseController
{
    protected UtenteModel $model;

    public function __construct()
    {
        $this->model = new UtenteModel();
    }

    public function index()
    {
        return view('admin/utenti/index', ['title' => 'Utenti']);
    }

    public function datatable()
    {
        $request = $this->request;

        $sortableColumns = ['id', 'nome', 'email', 'ruolo_nome', 'attivo'];

        $draw        = (int) $request->getPost('draw');
        $start       = (int) $request->getPost('start');
        $length      = (int) $request->getPost('length');
        $searchValue = $request->getPost('search')['value'] ?? '';

        $orderCol = (int) ($request->getPost('order')[0]['column'] ?? 0);
        $orderDir = strtolower($request->getPost('order')[0]['dir'] ?? 'asc');
        $orderDir = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'asc';
        $orderColumnName = $sortableColumns[$orderCol] ?? $sortableColumns[0];

        $recordsTotal = $this->model->countAllResults(false);

        $this->model->conRuolo();
        $builder = $this->model->conCreatoreModificatore();
        if (!empty($searchValue)) {
            $builder->groupStart()
                ->like('utenti.nome', $searchValue)
                ->orLike('utenti.email', $searchValue)
                ->orLike('ruoli.nome', $searchValue)
                ->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        $rows = $builder->orderBy($orderColumnName, $orderDir)
            ->get($length, $start)
            ->getResultArray();

        $puoModificare  = $this->haPermesso('utenti.edit');
        $puoEliminare   = $this->haPermesso('utenti.delete');
        $utenteInSessione = (int) session()->get('user_id');

        foreach ($rows as &$row) {
            $row['azioni'] = view('admin/utenti/_azioni', [
                'utente'         => $row,
                'puoModificare'  => $puoModificare,
                // Un utente non puo' eliminare se stesso: evita di restare bloccati fuori.
                'puoEliminare'   => $puoEliminare && (int) $row['id'] !== $utenteInSessione,
            ], ['debug' => false]);
            unset($row['password_hash']);
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }

    /**
     * Restituisce l'HTML del form (vuoto per la creazione, precompilato per la
     * modifica) da iniettare nel body della modale via AJAX.
     */
    public function form(?int $id = null)
    {
        $utente = null;

        if ($id !== null) {
            $utente = $this->model->find($id);
            if (!$utente) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Utente non trovato.']);
            }
            unset($utente['password_hash']);
        }

        $ruoli = (new RuoloModel())->orderBy('nome')->findAll();

        return $this->response->setJSON([
            'success' => true,
            'html'    => view('admin/utenti/_form', ['utente' => $utente, 'ruoli' => $ruoli], ['debug' => false]),
        ]);
    }

    public function store()
    {
        $rules = $this->model->validationRules;
        $rules['password'] = 'required|min_length[8]';

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        // La validazione e' gia' stata eseguita sopra: evita che il Model la rilanci
        // internamente con le regole grezze (query is_unique duplicata e inutile).
        $ok = $this->model->skipValidation(true)->insert([
            'nome'          => $this->request->getPost('nome'),
            'email'         => $this->request->getPost('email'),
            'password_hash' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
            'ruolo_id'      => $this->request->getPost('ruolo_id') ?: null,
            'attivo'        => $this->request->getPost('attivo') ? 1 : 0,
        ]);

        if ($ok === false) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->model->errors()]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Utente creato con successo.']);
    }

    public function update(int $id)
    {
        $utente = $this->model->find($id);
        if (!$utente) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Utente non trovato.']);
        }

        $rules = $this->model->validationRules;
        $rules['email'] = str_replace('{id}', (string) $id, $rules['email']);

        $password = (string) $this->request->getPost('password');
        $rules['password'] = 'permit_empty|min_length[8]';

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $data = [
            'nome'     => $this->request->getPost('nome'),
            'email'    => $this->request->getPost('email'),
            'ruolo_id' => $this->request->getPost('ruolo_id') ?: null,
            'attivo'   => $this->request->getPost('attivo') ? 1 : 0,
        ];

        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        // skipValidation: la regola is_unique e' gia' stata validata sopra CON l'esclusione
        // dell'id corrente. La validazione automatica del Model userebbe invece la regola
        // grezza del model (is_unique[...,id,{id}] con {id} non risolto, perche' 'id' non e'
        // tra i campi aggiornati) e rifiuterebbe qualsiasi update che non cambi l'email.
        $ok = $this->model->skipValidation(true)->update($id, $data);

        if ($ok === false) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->model->errors()]);
        }

        // Se l'utente ha modificato se stesso, la sessione riflette subito le nuove info.
        if ($id === (int) session()->get('user_id')) {
            $ruoloModel = new RuoloModel();
            $permessi   = $data['ruolo_id'] ? $ruoloModel->getPermessi($data['ruolo_id']) : [];
            $ruolo      = $data['ruolo_id'] ? $ruoloModel->find($data['ruolo_id']) : null;
            session()->set([
                'nome'       => $data['nome'],
                'email'      => $data['email'],
                'ruolo_id'   => $data['ruolo_id'],
                'ruolo_nome' => $ruolo['nome'] ?? null,
                'permessi'   => $permessi,
            ]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Utente aggiornato con successo.']);
    }

    public function delete(int $id)
    {
        if ($id === (int) session()->get('user_id')) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Non puoi eliminare il tuo stesso account.']);
        }

        if (!$this->model->find($id)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Utente non trovato.']);
        }

        $this->model->delete($id);

        return $this->response->setJSON(['success' => true, 'message' => 'Utente eliminato.']);
    }

    private function haPermesso(string $chiave): bool
    {
        return in_array($chiave, session()->get('permessi') ?? [], true);
    }
}
