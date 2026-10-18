<?php

namespace App\Controllers\Admin;

use App\Libraries\AllegatiBug;
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
        ['data' => 'allegati_html', 'label' => 'Allegati', 'ordina' => null, 'html' => true],
    ];

    protected array $ricerca = ['bug.titolo', 'bug.descrizione', 'pt.nome', 'pr.nome', 'sv.nome'];

    protected array $script = ['assets/js/allegati.js'];

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
        $n = (new AllegatiBug())->conteggi([(int) $riga['id']])[(int) $riga['id']] ?? 0;
        $riga['allegati_html'] = $n > 0 ? '<i class="bi bi-paperclip"></i> ' . $n : '';

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

        $archivio = new AllegatiBug();

        return [
            'bug'           => $bug,
            'transizioni'   => BugModel::TRANSIZIONI_SVILUPPATORE[$record['stato']] ?? [],
            'puoAggiornare' => $this->haPermesso('bug.update'),
            'allegati'      => $archivio->elenco((int) $record['id']),
            'puoAllegare'   => $this->puoAllegare(),
            'limiteAllegati' => AllegatiBug::limiteByte(),
        ];
    }

    /**
     * Chi lavora il bug (sviluppatore) e chi lo segnala (tester) possono aggiungere e
     * togliere allegati; chi puo' solo consultare no.
     */
    private function puoAllegare(): bool
    {
        return $this->haPermesso('bug.update') || $this->haPermesso('esecuzioni.create');
    }

    public function allega(int $id)
    {
        if (!$this->puoAllegare()) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Non hai i permessi necessari per questa operazione.']);
        }
        if (!$this->model->find($id)) {
            return $this->nonTrovato();
        }

        $archivio = new AllegatiBug();
        $file     = AllegatiBug::soloCaricati($this->request->getFileMultiple('allegati'));

        if ($file === []) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Scegli almeno un file da allegare.']);
        }

        $errore = $archivio->valida($file);
        if ($errore !== null) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $errore]);
        }

        $n = $archivio->salva($id, $file, session()->get('user_id'));

        return $this->response->setJSON(['success' => true, 'message' => $n === 1 ? 'Allegato aggiunto.' : "{$n} allegati aggiunti."]);
    }

    public function eliminaAllegato(int $allegatoId)
    {
        if (!$this->puoAllegare()) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Non hai i permessi necessari per questa operazione.']);
        }

        if (!(new AllegatiBug())->elimina($allegatoId)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Allegato non trovato.']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Allegato eliminato.']);
    }

    /**
     * Immagini raster in linea (per le anteprime), tutto il resto come download.
     * nosniff e CSP "sandbox" impediscono al browser di interpretare o eseguire il file.
     */
    public function allegato(int $allegatoId)
    {
        $archivio = new AllegatiBug();
        $allegato = $archivio->trova($allegatoId);
        $file     = $allegato ? $archivio->percorso($allegato) : null;

        if ($allegato === null || !is_file($file)) {
            return $this->response->setStatusCode(404)->setBody('Allegato non trovato.');
        }

        $inLinea = in_array(strtolower(pathinfo($allegato['nome_file'], PATHINFO_EXTENSION)), AllegatiBug::IMMAGINI, true);
        $nome    = str_replace(['"', '\\', "\r", "\n"], '_', $allegato['nome_originale']);

        return $this->response
            ->setHeader('Content-Type', $allegato['mime'])
            ->setHeader('Content-Disposition', ($inLinea ? 'inline' : 'attachment') . '; filename="' . $nome . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody((string) file_get_contents($file));
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
