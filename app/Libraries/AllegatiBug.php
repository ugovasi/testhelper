<?php

namespace App\Libraries;

use App\Models\AllegatoBugModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Validazione, salvataggio e lettura degli allegati dei bug.
 *
 * Sicurezza:
 *  - estensione ammessa da whitelist E tipo MIME rilevato dal contenuto coerente con essa
 *    (un .png che in realta' e' HTML viene rifiutato); niente SVG, HTML o eseguibili;
 *  - sul disco il nome e' casuale (mai quello dell'utente) e la cartella e' fuori da public/;
 *  - il download passa da BugController, che controlla i permessi e invia intestazioni
 *    che impediscono al browser di interpretare il file.
 */
class AllegatiBug
{
    public const MAX_BYTE = 10 * 1024 * 1024;
    public const MAX_FILE = 5;

    /** Estensione => tipi MIME accettati per quel contenuto. */
    public const TIPI = [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'log'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'json' => ['application/json', 'text/plain'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'mp4'  => ['video/mp4'],
        'webm' => ['video/webm'],
    ];

    /** Estensioni che il browser puo' mostrare in linea (tag img). */
    public const IMMAGINI = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    private AllegatoBugModel $modello;

    public function __construct(private readonly string $cartella = WRITEPATH . 'uploads/bug')
    {
        $this->modello = new AllegatoBugModel();
    }

    /**
     * Limite effettivo per file: quello dell'app, ma mai oltre upload_max_filesize di PHP
     * (oltre quel valore il file non arriva nemmeno all'applicazione).
     */
    public static function limiteByte(): int
    {
        $ini = trim((string) ini_get('upload_max_filesize'));
        if ($ini === '') {
            return self::MAX_BYTE;
        }

        $valore = (int) $ini;
        $limite = match (strtolower(substr($ini, -1))) {
            'g'     => $valore * 1024 ** 3,
            'm'     => $valore * 1024 ** 2,
            'k'     => $valore * 1024,
            default => $valore,
        };

        return $limite > 0 ? min(self::MAX_BYTE, $limite) : self::MAX_BYTE;
    }

    /**
     * Toglie i campi file lasciati vuoti (nessun file scelto).
     *
     * @param array<int, UploadedFile>|UploadedFile|null $file
     *
     * @return list<UploadedFile>
     */
    public static function soloCaricati(array|UploadedFile|null $file): array
    {
        $file = $file instanceof UploadedFile ? [$file] : ($file ?? []);

        return array_values(array_filter(
            $file,
            static fn ($f) => $f instanceof UploadedFile && $f->getError() !== UPLOAD_ERR_NO_FILE
        ));
    }

    /**
     * @param list<UploadedFile> $file
     *
     * @return string|null messaggio di errore, null se tutti i file sono accettabili
     */
    public function valida(array $file): ?string
    {
        if (count($file) > self::MAX_FILE) {
            return 'Puoi allegare al massimo ' . self::MAX_FILE . ' file per volta.';
        }

        $limite = self::limiteByte();

        foreach ($file as $f) {
            $nome = $this->nomePulito($f->getClientName());

            if ($f->getError() === UPLOAD_ERR_INI_SIZE || $f->getError() === UPLOAD_ERR_FORM_SIZE) {
                return "\"{$nome}\" supera la dimensione massima consentita ({$this->mb($limite)}).";
            }
            if ($f->getError() !== UPLOAD_ERR_OK) {
                return "Il caricamento di \"{$nome}\" non e riuscito.";
            }
            if ($f->getSize() > $limite) {
                return "\"{$nome}\" supera la dimensione massima consentita ({$this->mb($limite)}).";
            }

            $estensione = strtolower(pathinfo($f->getClientName(), PATHINFO_EXTENSION));
            if (!isset(self::TIPI[$estensione])) {
                return "\"{$nome}\": tipo di file non consentito. Ammessi: " . implode(', ', array_keys(self::TIPI)) . '.';
            }
            if (!in_array($f->getMimeType(), self::TIPI[$estensione], true)) {
                return "\"{$nome}\": il contenuto non corrisponde al tipo di file dichiarato.";
            }
        }

        return null;
    }

    /**
     * Salva file gia' validati con valida().
     *
     * @param list<UploadedFile> $file
     */
    public function salva(int $bugId, array $file, ?int $utenteId): int
    {
        $cartella = $this->cartella . '/' . $bugId;
        if (!is_dir($cartella)) {
            mkdir($cartella, 0750, true);
        }

        $salvati = 0;
        foreach ($file as $f) {
            $estensione = strtolower(pathinfo($f->getClientName(), PATHINFO_EXTENSION));
            $nomeFile   = bin2hex(random_bytes(16)) . '.' . $estensione;
            $mime       = $f->getMimeType();
            $dimensione = (int) $f->getSize();

            $f->move($cartella, $nomeFile);

            $this->modello->insert([
                'bug_id'         => $bugId,
                'nome_originale' => $this->nomePulito($f->getClientName()),
                'nome_file'      => $nomeFile,
                'mime'           => $mime,
                'dimensione'     => $dimensione,
                'caricato_da'    => $utenteId,
            ]);
            $salvati++;
        }

        return $salvati;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function elenco(int $bugId): array
    {
        $righe = db_connect()->table('bug_allegati a')
            ->select('a.*, u.nome as caricato_da_nome')
            ->join('utenti u', 'u.id = a.caricato_da', 'left')
            ->where('a.bug_id', $bugId)
            ->orderBy('a.id')
            ->get()->getResultArray();

        foreach ($righe as &$riga) {
            $riga['immagine'] = in_array(strtolower(pathinfo($riga['nome_file'], PATHINFO_EXTENSION)), self::IMMAGINI, true);
        }

        return $righe;
    }

    /**
     * Numero di allegati per ogni bug (per le liste).
     *
     * @param int[] $bugIds
     *
     * @return array<int, int>
     */
    public function conteggi(array $bugIds): array
    {
        if ($bugIds === []) {
            return [];
        }

        $righe = db_connect()->table('bug_allegati')->select('bug_id, count(*) as n')->whereIn('bug_id', $bugIds)->groupBy('bug_id')->get()->getResultArray();

        return array_map('intval', array_column($righe, 'n', 'bug_id'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trova(int $allegatoId): ?array
    {
        return $this->modello->find($allegatoId);
    }

    /**
     * @param array<string, mixed> $allegato
     */
    public function percorso(array $allegato): string
    {
        // basename(): il nome nel database e' generato da noi, ma il percorso non deve
        // poter uscire dalla cartella del bug nemmeno se la riga venisse alterata.
        return $this->cartella . '/' . (int) $allegato['bug_id'] . '/' . basename($allegato['nome_file']);
    }

    public function elimina(int $allegatoId): bool
    {
        $allegato = $this->trova($allegatoId);
        if ($allegato === null) {
            return false;
        }

        $file = $this->percorso($allegato);
        if (is_file($file)) {
            unlink($file);
        }

        $this->modello->delete($allegatoId);

        return true;
    }

    private function nomePulito(string $nome): string
    {
        $nome = basename(str_replace('\\', '/', $nome));
        $nome = preg_replace('/[\x00-\x1f\x7f]+/', '', $nome) ?? '';
        $nome = mb_substr($nome, -150);

        return $nome !== '' ? $nome : 'allegato';
    }

    private function mb(int $byte): string
    {
        return rtrim(rtrim(number_format($byte / 1048576, 1, ',', ''), '0'), ',') . ' MB';
    }
}
