<?php

namespace App\Filters;

use App\Models\LogAttivitaModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtro globale 'after': registra automaticamente chi ha fatto cosa e quando per
 * ogni operazione di scrittura (login/logout, creazione/modifica/eliminazione nei
 * moduli admin), cosi' nessun controller deve richiamare esplicitamente il logging.
 *
 * self::AZIONI e' l'unica whitelist: le richieste che non vi compaiono (index,
 * datatable, form) sono letture, non operazioni, e non producono una riga di log.
 */
class ActivityLogFilter implements FilterInterface
{
    private const AZIONI = [
        'login'                 => ['azione' => 'login', 'modulo' => 'auth'],
        'logout'                => ['azione' => 'logout', 'modulo' => 'auth'],
        'admin/utenti/store'    => ['azione' => 'create', 'modulo' => 'utenti'],
        'admin/utenti/update'   => ['azione' => 'update', 'modulo' => 'utenti'],
        'admin/utenti/delete'   => ['azione' => 'delete', 'modulo' => 'utenti'],
        'admin/ruoli/store'     => ['azione' => 'create', 'modulo' => 'ruoli'],
        'admin/ruoli/update'    => ['azione' => 'update', 'modulo' => 'ruoli'],
        'admin/ruoli/delete'    => ['azione' => 'delete', 'modulo' => 'ruoli'],
        'admin/permessi/store'  => ['azione' => 'create', 'modulo' => 'permessi'],
        'admin/permessi/update' => ['azione' => 'update', 'modulo' => 'permessi'],
        'admin/permessi/delete' => ['azione' => 'delete', 'modulo' => 'permessi'],
    ];

    private const ETICHETTE_AZIONE = [
        'create' => 'Creazione',
        'update' => 'Modifica',
        'delete' => 'Eliminazione',
    ];

    /** Campi POST candidati a descrivere l'entita' coinvolta, in ordine di preferenza. */
    private const CAMPI_DESCRITTIVI = ['nome', 'chiave', 'email'];

    public function before(RequestInterface $request, $arguments = null)
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $path = trim($request->getUri()->getPath(), '/');
        [$pathBase, $entitaId] = $this->separaId($path);

        if (!isset(self::AZIONI[$pathBase])) {
            return;
        }

        $azione = self::AZIONI[$pathBase]['azione'];
        $modulo = self::AZIONI[$pathBase]['modulo'];
        $esito  = $this->determinaEsito($azione, $response);

        $utenteId    = session()->get('user_id');
        $utenteNome  = session()->get('nome');
        $utenteEmail = session()->get('email');

        // Per un login fallito la sessione non contiene ancora nessun utente: usiamo
        // l'email digitata nel form per non perdere la tracciabilita' del tentativo.
        if ($azione === 'login' && $esito === 0) {
            $utenteEmail = (string) $request->getPost('email');
        }

        model(LogAttivitaModel::class)->insert([
            'utente_id'    => $utenteId,
            'utente_nome'  => $utenteNome,
            'utente_email' => $utenteEmail,
            'azione'       => $azione,
            'modulo'       => $modulo,
            'entita_id'    => $entitaId,
            'descrizione'  => $this->costruisciDescrizione($request, $azione, $modulo, $entitaId, $esito),
            'esito'        => $esito,
            'ip_address'   => $request->getIPAddress(),
        ]);
    }

    /**
     * Separa l'eventuale segmento numerico finale (id risorsa) dal resto del path,
     * es. "admin/utenti/update/5" -> ["admin/utenti/update", 5].
     *
     * @return array{0: string, 1: int|null}
     */
    private function separaId(string $path): array
    {
        $segmenti = explode('/', $path);
        $ultimo   = end($segmenti);

        if ($ultimo !== false && ctype_digit($ultimo)) {
            array_pop($segmenti);

            return [implode('/', $segmenti), (int) $ultimo];
        }

        return [$path, null];
    }

    private function determinaEsito(string $azione, ResponseInterface $response): int
    {
        if ($azione === 'login') {
            return session()->get('logged_in') ? 1 : 0;
        }

        $body = json_decode((string) $response->getBody(), true);

        if (is_array($body) && array_key_exists('success', $body)) {
            return $body['success'] ? 1 : 0;
        }

        return $response->getStatusCode() < 400 ? 1 : 0;
    }

    private function costruisciDescrizione(RequestInterface $request, string $azione, string $modulo, ?int $entitaId, int $esito): string
    {
        if ($azione === 'login') {
            return $esito === 1 ? 'Login effettuato' : 'Tentativo di login fallito';
        }

        if ($azione === 'logout') {
            return 'Logout';
        }

        $etichetta = null;
        foreach (self::CAMPI_DESCRITTIVI as $campo) {
            $valore = $request->getPost($campo);
            if ($valore !== null && $valore !== '') {
                $etichetta = $valore;

                break;
            }
        }

        $descrizione = self::ETICHETTE_AZIONE[$azione] . ' ' . $modulo;

        if ($entitaId !== null) {
            $descrizione .= " #{$entitaId}";
        }

        if ($etichetta !== null) {
            $descrizione .= ": {$etichetta}";
        }

        return $descrizione;
    }
}
