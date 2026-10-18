<?php

namespace App\Libraries;

use App\Models\BugModel;
use App\Models\EsecuzionePassaggioModel;
use App\Models\PianoTestModel;

/**
 * Regole del flusso test -> bug -> riverifica -> chiusura. Ogni metodo restituisce
 * ['success' => bool, 'message' => string] (piu' 'errors' per gli errori di campo),
 * pronto per essere serializzato in JSON dai controller.
 */
class EsecuzioneService
{
    public function __construct(
        private readonly PianoAvanzamento $avanzamento = new PianoAvanzamento(),
    ) {
    }

    /**
     * Registra un nuovo tentativo su un passaggio.
     *
     * - fallito: apre un bug (richiede $datiBug) oppure, se il passaggio aveva un bug
     *   gia' risolto, lo riapre mantenendone lo storico.
     * - passato su un passaggio con bug risolto: chiude il bug.
     *
     * $eseguitoIl (formato "Y-m-d\TH:i" dei campi datetime-local, o "Y-m-d H:i:s") imposta
     * data e ora dell'esecuzione; se vuoto vale l'istante attuale. Non puo' essere nel
     * futuro ne' precedere il tentativo precedente dello stesso passaggio.
     *
     * Il tempo impiegato (minuti interi, anche 0) e' obbligatorio per ogni esito tranne
     * "saltato": e' il tempo effettivo di quel tentativo, riverifiche comprese.
     *
     * @param array{titolo?: string, descrizione?: string, gravita?: string, assegnato_a?: int|string|null}|null $datiBug
     *
     * @return array{success: bool, message: string, errors?: array<string, string>, bug_id?: int}
     */
    public function registra(int $pianoCasoId, int $passaggioId, string $esito, ?string $note, string|int|null $minuti, ?array $datiBug, ?int $utenteId, ?string $eseguitoIl = null): array
    {
        $db = db_connect();

        if (!in_array($esito, EsecuzionePassaggioModel::ESITI, true)) {
            return ['success' => false, 'message' => 'Esito non valido.'];
        }

        $pianoCaso = $db->table('piano_casi')->where('id', $pianoCasoId)->get()->getRowArray();
        if ($pianoCaso === null) {
            return ['success' => false, 'message' => 'Caso del piano non trovato.'];
        }

        $piano = $db->table('piani_test')->where('id', $pianoCaso['piano_id'])->get()->getRowArray();
        if ($piano['stato'] === PianoTestModel::STATO_CHIUSO) {
            return ['success' => false, 'message' => 'Il piano e chiuso: non si possono registrare altre esecuzioni.'];
        }

        $caricato = $this->avanzamento->carica((int) $piano['id']);
        $passaggio = null;
        foreach ($caricato['casi'] as $caso) {
            if ((int) $caso['id'] !== $pianoCasoId) {
                continue;
            }
            foreach ($caso['passaggi'] as $candidato) {
                if ((int) $candidato['passaggio_id'] === $passaggioId) {
                    $passaggio = $candidato;
                }
            }
        }

        if ($passaggio === null) {
            return ['success' => false, 'message' => 'Il passaggio non appartiene a questo caso.'];
        }

        if (!$passaggio['eseguibile']) {
            return ['success' => false, 'message' => 'Il passaggio non e eseguibile: ' . match ($passaggio['stato']) {
                PianoAvanzamento::CHIUSO => 'e gia chiuso.',
                default                  => 'il bug collegato non e ancora stato risolto.',
            }];
        }

        $minutiImpiegati = null;
        if ($esito !== 'saltato') {
            $testo = trim((string) $minuti);
            if (!ctype_digit($testo)) {
                return ['success' => false, 'message' => 'Indica il tempo impiegato.', 'errors' => ['minuti_impiegati' => 'Inserisci i minuti impiegati (numero intero, anche 0).']];
            }
            $minutiImpiegati = (int) $testo;
        }

        $ora         = date('Y-m-d H:i:s');
        $dataEsito   = $ora;
        $precedente  = $passaggio['ultima_esecuzione']['eseguito_il'] ?? null;
        if ($eseguitoIl !== null && trim($eseguitoIl) !== '') {
            $dataEsito = self::normalizzaDataOra($eseguitoIl);
            $errore    = $dataEsito === null
                ? 'Data e ora non valide.'
                : $this->erroreIntervallo($dataEsito, $precedente, null);

            if ($errore !== null) {
                return ['success' => false, 'message' => $errore, 'errors' => ['eseguito_il' => $errore]];
            }
        }

        $bugEsistente = $passaggio['bug'];
        $riapre       = $esito === 'fallito' && $bugEsistente !== null;

        if ($esito === 'fallito' && !$riapre) {
            $errori = $this->validaBug($datiBug);
            if ($errori !== []) {
                return ['success' => false, 'message' => 'Dati del bug non validi.', 'errors' => $errori];
            }
        }

        $db->transStart();

        $esecuzioni = new EsecuzionePassaggioModel();
        $esecuzioneId = $esecuzioni->insert([
            'piano_caso_id' => $pianoCasoId,
            'passaggio_id'  => $passaggioId,
            'tentativo'     => ($passaggio['ultima_esecuzione']['tentativo'] ?? 0) + 1,
            'esito'         => $esito,
            'note'          => $note !== null && trim($note) !== '' ? trim($note) : null,
            'minuti_impiegati' => $minutiImpiegati,
            'eseguito_da'   => $utenteId,
            'eseguito_il'   => $dataEsito,
        ]);

        $bugs = new BugModel();
        $messaggio = 'Esito registrato.';
        $bugCoinvoltoId = null;

        if ($riapre) {
            $bugs->skipValidation(true)->update($bugEsistente['id'], [
                'stato'      => BugModel::STATO_RIAPERTO,
                'risolto_il' => null,
            ]);
            $messaggio = "Riverifica fallita: il bug #{$bugEsistente['id']} e stato riaperto.";
            $bugCoinvoltoId = (int) $bugEsistente['id'];
        } elseif ($esito === 'fallito') {
            $bugId = $bugs->insert([
                'titolo'                => trim($datiBug['titolo']),
                'descrizione'           => trim((string) ($datiBug['descrizione'] ?? '')) ?: null,
                'gravita'               => $datiBug['gravita'],
                'stato'                 => BugModel::STATO_APERTO,
                'esecuzione_origine_id' => $esecuzioneId,
                'assegnato_a'           => ($datiBug['assegnato_a'] ?? '') !== '' ? (int) $datiBug['assegnato_a'] : null,
            ]);
            $messaggio = "Passaggio fallito: aperto il bug #{$bugId}.";
            $bugCoinvoltoId = (int) $bugId;
        } elseif ($esito === 'passato' && $bugEsistente !== null) {
            $bugs->skipValidation(true)->update($bugEsistente['id'], [
                'stato'     => BugModel::STATO_CHIUSO,
                'chiuso_il' => $dataEsito,
            ]);
            $messaggio = "Riverifica superata: il bug #{$bugEsistente['id']} e chiuso.";
        }

        if ($piano['stato'] === PianoTestModel::STATO_BOZZA) {
            $db->table('piani_test')->where('id', $piano['id'])->update(['stato' => PianoTestModel::STATO_IN_CORSO, 'updated_at' => $ora]);
        }

        $db->transComplete();

        if (!$db->transStatus()) {
            return ['success' => false, 'message' => 'Errore nel salvataggio dell\'esito.'];
        }

        return ['success' => true, 'message' => $messaggio] + ($bugCoinvoltoId !== null ? ['bug_id' => $bugCoinvoltoId] : []);
    }

    /**
     * Corregge tempo, note e data/ora di un tentativo gia' registrato. L'esito non si
     * modifica: cambierebbe lo stato del bug collegato, per questo si registra un nuovo
     * tentativo (se il passaggio lo consente).
     *
     * @return array{success: bool, message: string, errors?: array<string, string>}
     */
    public function modifica(int $esecuzioneId, ?string $note, string|int|null $minuti, ?string $eseguitoIl): array
    {
        $db = db_connect();

        $esecuzione = $db->table('esecuzioni_passaggio')->where('id', $esecuzioneId)->get()->getRowArray();
        if ($esecuzione === null) {
            return ['success' => false, 'message' => 'Esecuzione non trovata.'];
        }

        $piano = $db->table('piano_casi pc')
            ->select('p.stato')
            ->join('piani_test p', 'p.id = pc.piano_id')
            ->where('pc.id', $esecuzione['piano_caso_id'])
            ->get()->getRowArray();
        if ($piano['stato'] === PianoTestModel::STATO_CHIUSO) {
            return ['success' => false, 'message' => 'Il piano e chiuso: le esecuzioni non si possono modificare.'];
        }

        $dati = ['note' => $note !== null && trim($note) !== '' ? trim($note) : null];

        if ($esecuzione['esito'] === 'saltato') {
            $dati['minuti_impiegati'] = null;
        } else {
            $testo = trim((string) $minuti);
            if (!ctype_digit($testo)) {
                return ['success' => false, 'message' => 'Indica il tempo impiegato.', 'errors' => ['minuti_impiegati' => 'Inserisci i minuti impiegati (numero intero, anche 0).']];
            }
            $dati['minuti_impiegati'] = (int) $testo;
        }

        if ($eseguitoIl === null || trim($eseguitoIl) === '') {
            return ['success' => false, 'message' => 'Indica data e ora.', 'errors' => ['eseguito_il' => 'Indica data e ora dell\'esecuzione.']];
        }

        $dataEsito = self::normalizzaDataOra($eseguitoIl);
        $errore    = $dataEsito === null ? 'Data e ora non valide.' : $this->erroreIntervallo($dataEsito, $this->vicino($esecuzione, '<'), $this->vicino($esecuzione, '>'));
        if ($errore !== null) {
            return ['success' => false, 'message' => $errore, 'errors' => ['eseguito_il' => $errore]];
        }
        $dati['eseguito_il'] = $dataEsito;

        $db->table('esecuzioni_passaggio')->where('id', $esecuzioneId)->update($dati);

        // La riverifica che ha chiuso un bug ne determina chiuso_il: se ne cambia la data,
        // la data di chiusura del bug la segue.
        if ($esecuzione['esito'] === 'passato' && $this->vicino($esecuzione, '>') === null) {
            $bugIds = array_column(
                $db->table('bug b')
                    ->select('b.id')
                    ->join('esecuzioni_passaggio e', 'e.id = b.esecuzione_origine_id')
                    ->where('e.piano_caso_id', $esecuzione['piano_caso_id'])
                    ->where('e.passaggio_id', $esecuzione['passaggio_id'])
                    ->where('b.stato', BugModel::STATO_CHIUSO)
                    ->get()->getResultArray(),
                'id'
            );

            if ($bugIds !== []) {
                $db->table('bug')->whereIn('id', $bugIds)->update(['chiuso_il' => $dataEsito]);
            }
        }

        return ['success' => true, 'message' => 'Esecuzione aggiornata.'];
    }

    /**
     * Accetta "Y-m-d\TH:i[:s]" (datetime-local) o "Y-m-d H:i[:s]" e restituisce
     * "Y-m-d H:i:s", oppure null se il valore non e' una data/ora reale.
     */
    public static function normalizzaDataOra(string $valore): ?string
    {
        $valore = str_replace('T', ' ', trim($valore));

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $formato) {
            $data = \DateTimeImmutable::createFromFormat('!' . $formato, $valore);
            $errori = \DateTimeImmutable::getLastErrors();

            if ($data !== false && ($errori === false || ($errori['warning_count'] === 0 && $errori['error_count'] === 0))) {
                return $data->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /**
     * Data/ora: non nel futuro (un minuto di tolleranza) e dentro i tentativi adiacenti.
     */
    private function erroreIntervallo(string $dataOra, ?string $precedente, ?string $successivo): ?string
    {
        if ($dataOra > date('Y-m-d H:i:s', strtotime('+1 minute'))) {
            return 'La data e l\'ora non possono essere nel futuro.';
        }

        if ($precedente !== null && $dataOra < $precedente) {
            return 'Non puo precedere il tentativo precedente (' . date('d/m/Y H:i', strtotime($precedente)) . ').';
        }

        if ($successivo !== null && $dataOra > $successivo) {
            return 'Non puo seguire il tentativo successivo (' . date('d/m/Y H:i', strtotime($successivo)) . ').';
        }

        return null;
    }

    /**
     * Data/ora del tentativo precedente ('<') o successivo ('>') dello stesso passaggio.
     *
     * @param array<string, mixed> $esecuzione
     */
    private function vicino(array $esecuzione, string $verso): ?string
    {
        $riga = db_connect()->table('esecuzioni_passaggio')
            ->select('eseguito_il')
            ->where('piano_caso_id', $esecuzione['piano_caso_id'])
            ->where('passaggio_id', $esecuzione['passaggio_id'])
            ->where('tentativo ' . $verso, $esecuzione['tentativo'])
            ->orderBy('tentativo', $verso === '<' ? 'desc' : 'asc')
            ->get(1)->getRowArray();

        return $riga['eseguito_il'] ?? null;
    }

    /**
     * Cambio di stato del bug da parte dello sviluppatore (vedi BugModel::TRANSIZIONI_SVILUPPATORE).
     *
     * @return array{success: bool, message: string}
     */
    public function cambiaStatoBug(int $bugId, string $nuovoStato, ?string $nota, ?int $utenteId): array
    {
        $bugs = new BugModel();
        $bug  = $bugs->find($bugId);

        if ($bug === null) {
            return ['success' => false, 'message' => 'Bug non trovato.'];
        }

        if (!in_array($nuovoStato, BugModel::TRANSIZIONI_SVILUPPATORE[$bug['stato']] ?? [], true)) {
            return ['success' => false, 'message' => "Passaggio di stato non consentito da \"{$bug['stato']}\" a \"{$nuovoStato}\"."];
        }

        $dati = ['stato' => $nuovoStato];

        // Chi prende in carico un bug non assegnato ne diventa l'assegnatario.
        if ($bug['assegnato_a'] === null && $utenteId !== null) {
            $dati['assegnato_a'] = $utenteId;
        }

        if ($nuovoStato === BugModel::STATO_RISOLTO) {
            $dati['risolto_il'] = date('Y-m-d H:i:s');
            if ($nota !== null && trim($nota) !== '') {
                $dati['note_risoluzione'] = trim($nota);
            }
        }

        $bugs->skipValidation(true)->update($bugId, $dati);

        return ['success' => true, 'message' => $nuovoStato === BugModel::STATO_RISOLTO
            ? 'Bug risolto: il passaggio e in attesa di riverifica.'
            : 'Bug preso in carico.'];
    }

    /**
     * Chiude il piano quando tutti i passaggi sono chiusi.
     *
     * @return array{success: bool, message: string}
     */
    public function chiudiPiano(int $pianoId): array
    {
        $caricato = $this->avanzamento->carica($pianoId);

        if ($caricato === null) {
            return ['success' => false, 'message' => 'Piano non trovato.'];
        }

        if ($caricato['piano']['stato'] === PianoTestModel::STATO_CHIUSO) {
            return ['success' => false, 'message' => 'Il piano e gia chiuso.'];
        }

        if (!$caricato['metriche']['completo']) {
            return ['success' => false, 'message' => 'Il piano non si puo chiudere: ci sono passaggi non ancora chiusi.'];
        }

        (new PianoTestModel())->skipValidation(true)->update($pianoId, [
            'stato'               => PianoTestModel::STATO_CHIUSO,
            'data_fine_effettiva' => date('Y-m-d'),
        ]);

        return ['success' => true, 'message' => 'Piano chiuso.'];
    }

    /**
     * @param array<string, mixed>|null $datiBug
     *
     * @return array<string, string>
     */
    private function validaBug(?array $datiBug): array
    {
        $errori = [];

        if ($datiBug === null || mb_strlen(trim((string) ($datiBug['titolo'] ?? ''))) < 3) {
            $errori['bug_titolo'] = 'Inserisci un titolo per il bug (almeno 3 caratteri).';
        }

        if (!in_array($datiBug['gravita'] ?? null, BugModel::GRAVITA, true)) {
            $errori['bug_gravita'] = 'Seleziona la gravita.';
        }

        $assegnato = $datiBug['assegnato_a'] ?? '';
        if ($assegnato !== '' && $assegnato !== null
            && db_connect()->table('utenti')->where('id', (int) $assegnato)->where('deleted_at', null)->countAllResults() === 0) {
            $errori['bug_assegnato_a'] = 'Utente non valido.';
        }

        return $errori;
    }
}
