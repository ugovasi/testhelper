<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AllegatiBug;
use App\Libraries\EsecuzioneService;
use App\Libraries\PianoAvanzamento;
use App\Models\UtenteModel;

/**
 * Esecuzione guidata dei casi di un piano, registrazione dei tempi e coda delle
 * riverifiche (passaggi il cui bug e' stato risolto dallo sviluppatore).
 */
class EsecuzioniController extends BaseController
{
    private PianoAvanzamento $avanzamento;
    private EsecuzioneService $service;

    public function __construct()
    {
        $this->avanzamento = new PianoAvanzamento();
        $this->service     = new EsecuzioneService($this->avanzamento);
    }

    public function caso(int $pianoCasoId)
    {
        $db        = db_connect();
        $pianoCaso = $db->table('piano_casi')->where('id', $pianoCasoId)->get()->getRowArray();

        if ($pianoCaso === null) {
            return redirect()->to(site_url('admin/piani'))->with('error', 'Caso del piano non trovato.');
        }

        $caricato = $this->avanzamento->carica((int) $pianoCaso['piano_id']);
        $caso     = current(array_filter($caricato['casi'], static fn ($c) => (int) $c['id'] === $pianoCasoId));

        $piano = $db->table('piani_test p')
            ->select('p.*, v.nome as versione_nome, pr.nome as prodotto_nome')
            ->join('versioni v', 'v.id = p.versione_id')
            ->join('prodotti pr', 'pr.id = v.prodotto_id')
            ->where('p.id', $pianoCaso['piano_id'])
            ->get()->getRowArray();

        $dettaglioCaso = $db->table('casi_test')->where('id', $caso['caso_id'])->get()->getRowArray();

        // Allegati dei bug di questo caso, per consultarli (es. in vista di una riverifica).
        $allegatiPerBug = [];
        $archivio = new AllegatiBug();
        foreach ($caso['passaggi'] as $passaggio) {
            if ($passaggio['bug'] !== null) {
                $allegatiPerBug[(int) $passaggio['bug']['id']] = $archivio->elenco((int) $passaggio['bug']['id']);
            }
        }

        return view('admin/esecuzioni/caso', [
            'title'         => $caso['titolo'],
            'piano'         => $piano,
            'caso'          => $caso,
            'precondizioni' => $dettaglioCaso['precondizioni'],
            'sviluppatori'  => (new UtenteModel())->where('attivo', 1)->orderBy('nome')->findAll(),
            'chiuso'        => $piano['stato'] === 'chiuso',
            'allegatiPerBug' => $allegatiPerBug,
            'limiteAllegati' => AllegatiBug::limiteByte(),
        ]);
    }

    public function registra()
    {
        $post = $this->request->getPost();

        // Gli allegati si controllano prima di registrare: un file non valido non deve
        // lasciare un bug aperto senza gli screenshot che il tester voleva allegare.
        $allegati = new AllegatiBug();
        $file     = ($post['esito'] ?? '') === 'fallito' ? AllegatiBug::soloCaricati($this->request->getFileMultiple('allegati')) : [];
        $erroreFile = $allegati->valida($file);
        if ($erroreFile !== null) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $erroreFile, 'errors' => ['allegati[]' => $erroreFile]]);
        }

        $esito = $this->service->registra(
            (int) ($post['piano_caso_id'] ?? 0),
            (int) ($post['passaggio_id'] ?? 0),
            (string) ($post['esito'] ?? ''),
            $post['note'] ?? null,
            $post['minuti_impiegati'] ?? null,
            [
                'titolo'      => $post['bug_titolo'] ?? '',
                'descrizione' => $post['bug_descrizione'] ?? '',
                'gravita'     => $post['bug_gravita'] ?? '',
                'assegnato_a' => $post['bug_assegnato_a'] ?? '',
            ],
            session()->get('user_id'),
            $post['eseguito_il'] ?? null
        );

        if ($esito['success'] && isset($esito['bug_id']) && $file !== []) {
            $n = $allegati->salva($esito['bug_id'], $file, session()->get('user_id'));
            $esito['message'] .= " Allegati salvati: {$n}.";
        }

        return $this->response->setStatusCode($esito['success'] ? 200 : 422)->setJSON($esito);
    }

    public function modifica(int $esecuzioneId)
    {
        $esito = $this->service->modifica(
            $esecuzioneId,
            $this->request->getPost('note'),
            $this->request->getPost('minuti_impiegati'),
            $this->request->getPost('eseguito_il')
        );

        return $this->response->setStatusCode($esito['success'] ? 200 : 422)->setJSON($esito);
    }

    public function riverifiche()
    {
        $tutti  = (bool) $this->request->getGet('tutti');
        $utente = (int) session()->get('user_id');

        $piani = db_connect()->table('piani_test')->where('stato !=', 'chiuso');
        if (!$tutti) {
            $piani->where('assegnatario_id', $utente);
        }

        $righe = [];
        foreach ($piani->orderBy('data_fine_prevista')->get()->getResultArray() as $piano) {
            $caricato = $this->avanzamento->carica((int) $piano['id']);

            foreach ($caricato['casi'] as $caso) {
                foreach ($caso['passaggi'] as $passaggio) {
                    if ($passaggio['stato'] === PianoAvanzamento::DA_RIVERIFICARE) {
                        $righe[] = ['piano' => $piano, 'caso' => $caso, 'passaggio' => $passaggio];
                    }
                }
            }
        }

        return view('admin/esecuzioni/riverifiche', ['title' => 'Riverifiche', 'righe' => $righe, 'tutti' => $tutti]);
    }
}
