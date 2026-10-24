<?php

namespace App\Controllers\Admin;

use App\Models\CasoTestModel;

/**
 * Libreria dei casi di test, con i passaggi modificati nello stesso form modale.
 * I passaggi arrivano come passaggi[N][id|azione|risultato_atteso].
 */
class CasiTestController extends AdminCrudController
{
    protected string $vista          = 'admin/casi';
    protected string $titolo         = 'Casi di test';
    protected string $permesso       = 'casi';
    protected string $slug           = 'casi';
    protected string $etichetta      = 'caso di test';
    protected string $campoEtichetta = 'titolo';

    protected array $colonne = [
        ['data' => 'id', 'label' => 'ID', 'ordina' => 'casi_test.id'],
        ['data' => 'suite_nome', 'label' => 'Suite', 'ordina' => 'suite.nome'],
        ['data' => 'titolo', 'label' => 'Titolo', 'ordina' => 'casi_test.titolo'],
        ['data' => 'priorita_html', 'label' => 'Priorita', 'ordina' => 'casi_test.priorita', 'html' => true],
        ['data' => 'stima_minuti', 'label' => 'Stima (min)', 'ordina' => 'casi_test.stima_minuti'],
        ['data' => 'n_passaggi', 'label' => 'Passaggi', 'ordina' => null],
    ];

    protected array $script = ['assets/js/casi-form.js', 'assets/js/casi-dettaglio.js'];

    protected array $ricerca = ['casi_test.titolo', 'suite.nome', 'prodotti.nome'];
    protected array $campi   = ['suite_id', 'titolo', 'precondizioni', 'priorita'];

    private const BADGE_PRIORITA = ['alta' => 'danger', 'media' => 'warning', 'bassa' => 'secondary'];

    public function __construct()
    {
        $this->model = new CasoTestModel();
    }

    protected function query()
    {
        // La sottoquery e' testo grezzo: il builder non ne prefissa le tabelle, quindi
        // il prefisso (vuoto in produzione, "db_" nei test) va applicato a mano.
        $db        = db_connect();
        $passaggi  = $db->prefixTable('passaggi');
        $casi      = $db->prefixTable('casi_test');

        return parent::query()
            ->select("suite.nome as suite_nome, prodotti.nome as prodotto_nome, (select count(*) from {$passaggi} where {$passaggi}.caso_id = {$casi}.id) as n_passaggi")
            ->join('suite', 'suite.id = casi_test.suite_id')
            ->join('prodotti', 'prodotti.id = suite.prodotto_id');
    }

    protected function preparaRiga(array $riga): array
    {
        $riga['suite_nome']    = $riga['prodotto_nome'] . ' / ' . $riga['suite_nome'];
        $riga['priorita_html'] = '<span class="badge text-bg-' . (self::BADGE_PRIORITA[$riga['priorita']] ?? 'secondary') . '">'
            . esc(ucfirst($riga['priorita'])) . '</span>';

        return $riga;
    }

    protected function azioniExtra(array $riga): string
    {
        return '<button type="button" class="btn btn-outline-secondary" data-caso-dettaglio data-url="'
            . esc(site_url('admin/casi/dettaglio/' . $riga['id']), 'attr') . '" data-titolo="' . esc($riga['titolo'], 'attr')
            . '" title="Dettaglio e passaggi"><i class="bi bi-eye"></i></button>';
    }

    /**
     * Scheda di sola lettura del caso: dati in cima, poi i passaggi con azione, risultato
     * atteso e tempo stimato. Stesso contratto JSON delle form (html pronto per la modale).
     */
    public function dettaglio(int $id)
    {
        $caso = $this->model->conCreatoreModificatore()
            ->select('suite.nome as suite_nome, prodotti.nome as prodotto_nome')
            ->join('suite', 'suite.id = casi_test.suite_id')
            ->join('prodotti', 'prodotti.id = suite.prodotto_id')
            ->where('casi_test.id', $id)
            ->get()->getRowArray();

        if ($caso === null) {
            return $this->nonTrovato();
        }

        return $this->response->setJSON([
            'success' => true,
            'html'    => view('admin/casi/_dettaglio', [
                'caso'     => $caso,
                'passaggi' => $this->model->getPassaggi($id),
                'piani'    => $this->model->utilizziInPiani($id),
            ], ['debug' => false]),
        ]);
    }

    protected function datiForm(?array $record): array
    {
        $suite = db_connect()->table('suite')
            ->select('suite.id, suite.nome, prodotti.nome as prodotto_nome')
            ->join('prodotti', 'prodotti.id = suite.prodotto_id')
            ->orderBy('prodotti.nome')->orderBy('suite.nome')
            ->get()->getResultArray();

        return [
            'suite'    => $suite,
            'passaggi' => $record ? $this->model->getPassaggi((int) $record['id']) : [],
        ];
    }

    /**
     * @return list<array{id?: string, azione: string, risultato_atteso?: string, stima_minuti?: string}>
     */
    private function righePassaggi(): array
    {
        $passaggi = $this->request->getPost('passaggi');

        return is_array($passaggi) ? array_values(array_filter($passaggi, 'is_array')) : [];
    }

    protected function validaExtra(?int $id): array
    {
        $righe  = $this->righePassaggi();
        $errori = [];

        if ($righe === []) {
            $errori['passaggi'] = 'Aggiungi almeno un passaggio.';
        }

        foreach ($righe as $riga) {
            if (trim((string) ($riga['azione'] ?? '')) === '') {
                $errori['passaggi'] = 'Ogni passaggio deve avere un\'azione.';
            }

            $stima = trim((string) ($riga['stima_minuti'] ?? ''));
            if (!ctype_digit($stima) || (int) $stima < 1) {
                $errori['passaggi'] = 'Indica per ogni passaggio il tempo stimato in minuti (almeno 1).';
            }
        }

        if ($id !== null) {
            $bloccati = $this->model->passaggiNonEliminabili($id, $righe);
            if ($bloccati !== []) {
                $errori['passaggi'] = 'Non puoi eliminare il passaggio ' . implode(', ', $bloccati) . ': ha gia esecuzioni registrate.';
            }
        }

        return $errori;
    }

    protected function dopoSalvataggio(int $id, bool $nuovo): void
    {
        $this->model->sincronizzaPassaggi($id, $this->righePassaggi());
    }

    protected function bloccoEliminazione(int $id): ?string
    {
        return $this->model->utilizziInPiani($id) > 0
            ? 'Impossibile eliminare: il caso e incluso in uno o piu piani di test.'
            : null;
    }
}
