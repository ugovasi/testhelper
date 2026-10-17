<?php

namespace App\Controllers\Admin;

use App\Libraries\EsecuzioneService;
use App\Libraries\PianoAvanzamento;
use App\Models\PianoTestModel;
use App\Models\UtenteModel;

/**
 * Piani di test: anagrafica (CRUD in modale, con selezione dei casi della libreria)
 * piu' il cruscotto di dettaglio con avanzamento, ritardo e proiezione di fine.
 */
class PianiTestController extends AdminCrudController
{
    protected string $vista     = 'admin/piani';
    protected string $titolo    = 'Piani di test';
    protected string $permesso  = 'piani';
    protected string $slug      = 'piani';
    protected string $etichetta = 'piano di test';

    protected array $colonne = [
        ['data' => 'id', 'label' => 'ID', 'ordina' => 'piani_test.id'],
        ['data' => 'nome_html', 'label' => 'Piano', 'ordina' => 'piani_test.nome', 'html' => true],
        ['data' => 'versione_completa', 'label' => 'Prodotto / versione', 'ordina' => 'prodotti.nome'],
        ['data' => 'assegnatario_nome', 'label' => 'Tester', 'ordina' => 'ass.nome'],
        ['data' => 'periodo', 'label' => 'Periodo', 'ordina' => 'piani_test.data_inizio'],
        ['data' => 'avanzamento_html', 'label' => 'Avanzamento', 'ordina' => null, 'html' => true],
        ['data' => 'stato_html', 'label' => 'Stato', 'ordina' => 'piani_test.stato', 'html' => true],
    ];

    protected array $ricerca = ['piani_test.nome', 'versioni.nome', 'prodotti.nome', 'ass.nome'];
    protected array $campi   = ['versione_id', 'nome', 'assegnatario_id', 'data_inizio', 'data_fine_prevista', 'quota_riverifica'];

    private readonly PianoAvanzamento $avanzamento;

    public function __construct()
    {
        $this->model       = new PianoTestModel();
        $this->avanzamento = new PianoAvanzamento();
    }

    protected function query()
    {
        return parent::query()
            ->select('versioni.nome as versione_nome, prodotti.nome as prodotto_nome, ass.nome as assegnatario_nome')
            ->join('versioni', 'versioni.id = piani_test.versione_id')
            ->join('prodotti', 'prodotti.id = versioni.prodotto_id')
            ->join('utenti ass', 'ass.id = piani_test.assegnatario_id', 'left');
    }

    protected function preparaRiga(array $riga): array
    {
        $riga['versione_completa'] = $riga['prodotto_nome'] . ' ' . $riga['versione_nome'];
        $riga['periodo']           = $riga['data_inizio'] . ' → ' . $riga['data_fine_prevista'];

        $url = site_url('admin/piani/dettaglio/' . $riga['id']);
        $riga['nome_html'] = '<a href="' . esc($url, 'attr') . '">' . esc($riga['nome']) . '</a>';

        $riga['stato_html'] = view('admin/piani/_badge_stato', ['stato' => $riga['stato']], ['debug' => false]);

        $caricato = $this->avanzamento->carica((int) $riga['id']);
        $riga['avanzamento_html'] = view('admin/piani/_barra', ['metriche' => $caricato['metriche']], ['debug' => false]);

        return $riga;
    }

    protected function azioniExtra(array $riga): string
    {
        return '<a class="btn btn-outline-secondary" href="' . esc(site_url('admin/piani/dettaglio/' . $riga['id']), 'attr')
            . '" title="Cruscotto"><i class="bi bi-bar-chart-line"></i></a>';
    }

    protected function datiForm(?array $record): array
    {
        $casi = db_connect()->table('casi_test c')
            ->select('c.id, c.titolo, c.stima_minuti, s.nome as suite_nome, p.nome as prodotto_nome')
            ->join('suite s', 's.id = c.suite_id')
            ->join('prodotti p', 'p.id = s.prodotto_id')
            ->orderBy('p.nome')->orderBy('s.nome')->orderBy('c.titolo')
            ->get()->getResultArray();

        $versioni = db_connect()->table('versioni v')
            ->select('v.id, v.nome, p.nome as prodotto_nome')
            ->join('prodotti p', 'p.id = v.prodotto_id')
            ->orderBy('p.nome')->orderBy('v.nome')
            ->get()->getResultArray();

        return [
            'versioni'    => $versioni,
            'tester'      => (new UtenteModel())->where('attivo', 1)->orderBy('nome')->findAll(),
            'casi'        => $casi,
            'casiPiano'   => $record ? array_map('intval', array_column($this->model->getPianoCasi((int) $record['id']), 'caso_id')) : [],
            'bloccato'    => $record && $record['stato'] === PianoTestModel::STATO_CHIUSO,
        ];
    }

    /**
     * @return int[]
     */
    private function casiSelezionati(): array
    {
        return array_map('intval', (array) $this->request->getPost('casi'));
    }

    protected function validaExtra(?int $id): array
    {
        $errori = [];
        $inizio = (string) $this->request->getPost('data_inizio');
        $fine   = (string) $this->request->getPost('data_fine_prevista');

        if ($inizio !== '' && $fine !== '' && $fine < $inizio) {
            $errori['data_fine_prevista'] = 'La fine prevista non puo precedere l\'inizio.';
        }

        $casi = $this->casiSelezionati();
        if ($casi === []) {
            $errori['casi'] = 'Seleziona almeno un caso di test.';
        }

        if ($id !== null) {
            if ($this->model->find($id)['stato'] === PianoTestModel::STATO_CHIUSO) {
                $errori['nome'] = 'Il piano e chiuso e non si puo modificare.';
            }

            $bloccati = $this->model->casiNonRimovibili($id, $casi);
            if ($bloccati !== []) {
                $errori['casi'] = 'Non puoi rimuovere casi gia eseguiti: ' . implode(', ', $bloccati) . '.';
            }
        }

        return $errori;
    }

    protected function dopoSalvataggio(int $id, bool $nuovo): void
    {
        $this->model->sincronizzaCasi($id, $this->casiSelezionati());
    }

    protected function bloccoEliminazione(int $id): ?string
    {
        return $this->model->haEsecuzioni($id)
            ? 'Impossibile eliminare: il piano ha esecuzioni registrate.'
            : null;
    }

    public function dettaglio(int $id)
    {
        $caricato = $this->avanzamento->carica($id);

        if ($caricato === null) {
            return redirect()->to(site_url('admin/piani'))->with('error', 'Piano non trovato.');
        }

        $piano = db_connect()->table('piani_test p')
            ->select('p.*, v.nome as versione_nome, pr.nome as prodotto_nome, u.nome as assegnatario_nome')
            ->join('versioni v', 'v.id = p.versione_id')
            ->join('prodotti pr', 'pr.id = v.prodotto_id')
            ->join('utenti u', 'u.id = p.assegnatario_id', 'left')
            ->where('p.id', $id)
            ->get()->getRowArray();

        return view('admin/piani/dettaglio', [
            'title'    => $piano['nome'],
            'piano'    => $piano,
            'casi'     => $caricato['casi'],
            'metriche' => $caricato['metriche'],
        ]);
    }

    public function chiudi(int $id)
    {
        $esito = (new EsecuzioneService())->chiudiPiano($id);

        return $this->response->setStatusCode($esito['success'] ? 200 : 422)->setJSON($esito);
    }
}
