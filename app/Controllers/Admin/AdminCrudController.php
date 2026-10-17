<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\BaseModel;

/**
 * Scheletro comune dei moduli CRUD con form in modale (stesso contratto JSON di
 * RuoliController/UtentiController, consumato da public/assets/js/admin-crud.js).
 * Ogni modulo dichiara solo le proprie particolarita': colonne, regole, query con
 * join e i controlli prima di eliminare.
 */
abstract class AdminCrudController extends BaseController
{
    protected BaseModel $model;

    /** Cartella delle viste del modulo, che deve contenere _form.php. */
    protected string $vista;

    protected string $titolo;

    /** Prefisso dei permessi (es. "prodotti" -> prodotti.view/create/edit/delete). */
    protected string $permesso;

    /** Script aggiuntivi della pagina indice (percorsi sotto public/). @var list<string> */
    protected array $script = [];

    /** Campo della riga mostrato nella conferma di eliminazione. */
    protected string $campoEtichetta = 'nome';

    protected string $titoloModifica = 'Modifica';

    /** Segmento di route sotto admin/, es. "prodotti" -> admin/prodotti. */
    protected string $slug;

    /** Nome singolare, minuscolo, con l'articolo grammaticale per i messaggi. */
    protected string $etichetta;

    /** "o" (maschile) o "a" (femminile): "creato"/"creata". */
    protected string $genere = 'o';

    /**
     * Colonne della DataTable, nell'ordine di visualizzazione (id compreso).
     * 'ordina' e' la colonna SQL qualificata per l'ordinamento (null = non ordinabile),
     * 'html' indica che la cella contiene HTML gia' preparato e escapato dal controller.
     *
     * @var list<array{data: string, label: string, ordina?: string|null, html?: bool}>
     */
    protected array $colonne = [];

    /** Colonne SQL qualificate su cui agisce la ricerca della DataTable. @var list<string> */
    protected array $ricerca = [];

    /** Campi POST che finiscono nel model (le sottoclassi possono rifinirli in dati()). @var list<string> */
    protected array $campi = [];

    /**
     * Query della DataTable: di base le colonne del model piu' creatore/modificatore.
     * Le sottoclassi aggiungono select()/join() per i campi delle tabelle collegate.
     */
    protected function query()
    {
        return $this->model->conCreatoreModificatore();
    }

    /**
     * Dati extra per _form.php (es. elenco dei prodotti per una select).
     *
     * @param array<string, mixed>|null $record
     *
     * @return array<string, mixed>
     */
    protected function datiForm(?array $record): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function regole(?int $id): array
    {
        $regole = [];
        foreach ($this->model->validationRules as $campo => $regola) {
            $regole[$campo] = str_replace('{id}', (string) ($id ?? 0), $regola);
        }

        return $regole;
    }

    /**
     * Controlli oltre alle regole del model (coerenza tra campi, vincoli sullo storico).
     *
     * @return array<string, string> errori per campo
     */
    protected function validaExtra(?int $id): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function dati(): array
    {
        $dati = [];
        foreach ($this->campi as $campo) {
            $valore = $this->request->getPost($campo);
            $dati[$campo] = is_string($valore) && trim($valore) === '' ? null : $valore;
        }

        return $dati;
    }

    protected function dopoSalvataggio(int $id, bool $nuovo): void
    {
    }

    /**
     * @return string|null messaggio di blocco, null se l'eliminazione e' consentita
     */
    protected function bloccoEliminazione(int $id): ?string
    {
        return null;
    }

    /**
     * @param array<string, mixed> $riga
     *
     * @return array<string, mixed>
     */
    protected function preparaRiga(array $riga): array
    {
        return $riga;
    }

    /**
     * HTML (gia' escapato) di pulsanti aggiuntivi nella colonna Azioni.
     *
     * @param array<string, mixed> $riga
     */
    protected function azioniExtra(array $riga): string
    {
        return '';
    }

    /** Permesso che mostra il pulsante di modifica nella colonna Azioni. */
    protected function permessoModifica(): string
    {
        return "{$this->permesso}.edit";
    }

    public function index()
    {
        return view('admin/crud/index', [
            'title' => $this->titolo,
            'crud'  => [
                'slug'      => $this->slug,
                'permesso'  => $this->permesso,
                'etichetta' => $this->etichetta,
                'genere'    => $this->genere,
                'colonne'   => $this->colonne,
                'script'    => $this->script,
            ],
        ]);
    }

    public function datatable()
    {
        $request = $this->request;

        $draw        = (int) $request->getPost('draw');
        $start       = (int) $request->getPost('start');
        $length      = (int) $request->getPost('length');
        $searchValue = $request->getPost('search')['value'] ?? '';

        $orderCol = (int) ($request->getPost('order')[0]['column'] ?? 0);
        $orderDir = strtolower($request->getPost('order')[0]['dir'] ?? 'desc');
        $orderDir = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'desc';
        $orderColumnName = ($this->colonne[$orderCol]['ordina'] ?? null) ?: $this->model->table . '.id';

        $recordsTotal = db_connect()->table($this->model->table)->countAllResults();

        $builder = $this->query();
        if ($searchValue !== '' && $this->ricerca !== []) {
            $builder->groupStart();
            foreach ($this->ricerca as $indice => $colonna) {
                $indice === 0 ? $builder->like($colonna, $searchValue) : $builder->orLike($colonna, $searchValue);
            }
            $builder->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        $rows = $builder->orderBy($orderColumnName, $orderDir)
            ->get($length > 0 ? $length : 10, $start)
            ->getResultArray();

        $puoModificare = $this->haPermesso($this->permessoModifica());
        $puoEliminare  = $this->haPermesso("{$this->permesso}.delete");

        foreach ($rows as &$row) {
            $row = $this->preparaRiga($row);
            $row['azioni'] = view('admin/crud/_azioni', [
                'id'            => (int) $row['id'],
                'etichettaRiga' => (string) ($row[$this->campoEtichetta] ?? ''),
                'extra'         => $this->azioniExtra($row),
                'titoloModifica' => $this->titoloModifica,
                'puoModificare' => $puoModificare,
                'puoEliminare'  => $puoEliminare && $this->bloccoEliminazione((int) $row['id']) === null,
            ], ['debug' => false]);
        }
        unset($row);

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }

    public function form(?int $id = null)
    {
        $record = null;

        if ($id !== null) {
            $record = $this->model->find($id);
            if (!$record) {
                return $this->nonTrovato();
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'html'    => view("{$this->vista}/_form", array_merge(['record' => $record], $this->datiForm($record)), ['debug' => false]),
        ]);
    }

    public function store()
    {
        return $this->salva(null);
    }

    public function update(int $id)
    {
        if (!$this->model->find($id)) {
            return $this->nonTrovato();
        }

        return $this->salva($id);
    }

    public function delete(int $id)
    {
        if (!$this->model->find($id)) {
            return $this->nonTrovato();
        }

        $blocco = $this->bloccoEliminazione($id);
        if ($blocco !== null) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $blocco]);
        }

        $this->model->delete($id);

        return $this->response->setJSON(['success' => true, 'message' => ucfirst($this->etichetta) . ' eliminat' . $this->genere . '.']);
    }

    private function salva(?int $id)
    {
        $errori = [];
        if (!$this->validate($this->regole($id))) {
            $errori = $this->validator->getErrors();
        }
        $errori = array_merge($this->validaExtra($id), $errori);

        if ($errori !== []) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $errori]);
        }

        // Validazione gia' eseguita sopra con le regole complete (is_unique incluso):
        // evita che il Model la ripeta con le regole grezze.
        $db = db_connect();
        $db->transStart();

        $model = $this->model->skipValidation(true);
        if ($id === null) {
            $id = $model->insert($this->dati());
            $ok = $id !== false;
            $nuovo = true;
        } else {
            $ok = $model->update($id, $this->dati());
            $nuovo = false;
        }

        if ($ok === false) {
            $db->transComplete();

            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => $this->model->errors()]);
        }

        $this->dopoSalvataggio((int) $id, $nuovo);
        $db->transComplete();

        return $this->response->setJSON([
            'success' => true,
            'message' => ucfirst($this->etichetta) . ($nuovo ? ' creat' : ' aggiornat') . $this->genere . ' con successo.',
        ]);
    }

    protected function nonTrovato()
    {
        return $this->response->setStatusCode(404)->setJSON([
            'success' => false,
            'message' => ucfirst($this->etichetta) . ' non trovat' . $this->genere . '.',
        ]);
    }

    protected function haPermesso(string $chiave): bool
    {
        return in_array($chiave, session()->get('permessi') ?? [], true);
    }
}
