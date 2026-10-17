<?php

namespace App\Controllers\Admin;

use App\Libraries\EsecuzioneService;
use App\Models\BugModel;

/**
 * Elenco e gestione dei bug. Lo sviluppatore li porta in lavorazione e poi a "risolto";
 * "chiuso" e "riaperto" li imposta solo la riverifica del tester (EsecuzioneService).
 * Non esistono creazione ed eliminazione manuali: un bug nasce da un passaggio fallito.
 */
class BugController extends AdminCrudController
{
    protected string $vista          = 'admin/bug';
    protected string $titolo         = 'Bug';
    protected string $permesso       = 'bug';
    protected string $slug           = 'bug';
    protected string $etichetta      = 'bug';
    protected string $campoEtichetta = 'titolo';
    protected string $titoloModifica = 'Dettaglio';

    protected array $colonne = [
        ['data' => 'id', 'label' => 'ID', 'ordina' => 'bug.id'],
        ['data' => 'titolo', 'label' => 'Titolo', 'ordina' => 'bug.titolo'],
        ['data' => 'origine', 'label' => 'Piano / passaggio', 'ordina' => 'pt.nome'],
        ['data' => 'gravita_html', 'label' => 'Gravita', 'ordina' => 'bug.gravita', 'html' => true],
        ['data' => 'stato_html', 'label' => 'Stato', 'ordina' => 'bug.stato', 'html' => true],
        ['data' => 'assegnato_nome', 'label' => 'Assegnato a', 'ordina' => 'sv.nome'],
    ];

    protected array $ricerca = ['bug.titolo', 'bug.descrizione', 'pt.nome', 'pr.nome', 'sv.nome'];

    private const BADGE_GRAVITA = ['bassa' => 'secondary', 'media' => 'info', 'alta' => 'warning', 'critica' => 'danger'];

    private const BADGE_STATO = [
        'aperto'         => 'danger',
        'in_lavorazione' => 'primary',
        'risolto'        => 'warning',
        'chiuso'         => 'success',
        'riaperto'       => 'danger',
    ];

    public function __construct()
    {
        $this->model = new BugModel();
    }

    protected function query()
    {
        return $this->model->conContesto();
    }

    protected function permessoModifica(): string
    {
        // Chiunque veda i bug puo' aprire il dettaglio; i controlli di stato nel form
        // compaiono solo con bug.update (vedi admin/bug/_form).
        return 'bug.view';
    }

    protected function preparaRiga(array $riga): array
    {
        $riga['origine'] = $riga['piano_nome'] . ' / ' . $riga['caso_titolo'] . ' / passaggio ' . $riga['passaggio_ordine'];
        $riga['gravita_html'] = '<span class="badge text-bg-' . (self::BADGE_GRAVITA[$riga['gravita']] ?? 'secondary') . '">'
            . esc(ucfirst($riga['gravita'])) . '</span>';
        $riga['stato_html'] = '<span class="badge text-bg-' . (self::BADGE_STATO[$riga['stato']] ?? 'secondary') . '">'
            . esc(ucfirst(str_replace('_', ' ', $riga['stato']))) . '</span>';

        return $riga;
    }

    protected function datiForm(?array $record): array
    {
        $bug = $this->model->conContesto()->where('bug.id', $record['id'])->get()->getRowArray();

        return [
            'bug'         => $bug,
            'transizioni' => BugModel::TRANSIZIONI_SVILUPPATORE[$record['stato']] ?? [],
            'puoAggiornare' => $this->haPermesso('bug.update'),
        ];
    }

    public function update(int $id)
    {
        if (!$this->model->find($id)) {
            return $this->nonTrovato();
        }

        $esito = (new EsecuzioneService())->cambiaStatoBug(
            $id,
            (string) $this->request->getPost('stato'),
            $this->request->getPost('note_risoluzione'),
            session()->get('user_id')
        );

        if (!$esito['success']) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'errors' => ['stato' => $esito['message']]]);
        }

        return $this->response->setJSON($esito);
    }
}
