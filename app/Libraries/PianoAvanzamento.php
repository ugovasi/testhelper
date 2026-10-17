<?php

namespace App\Libraries;

use App\Models\BugModel;
use DateTimeImmutable;

/**
 * Calcola lo stato di ogni passaggio di un piano e le metriche di avanzamento
 * (stima, ritardo, proiezione di fine). Nulla e' memorizzato: tutto deriva dalle
 * esecuzioni (ultimo tentativo per passaggio) e dai bug collegati.
 *
 * Stato di un passaggio, nell'ordine di precedenza:
 *  1. bug risolto, in attesa di riverifica        -> da_riverificare
 *  2. bug ancora aperto / in lavorazione / riaperto -> fallito
 *  3. altrimenti, esito dell'ultimo tentativo      -> passato (= chiuso), bloccato, saltato
 *  4. nessuna esecuzione                           -> da_eseguire
 */
class PianoAvanzamento
{
    public const DA_ESEGUIRE     = 'da_eseguire';
    public const CHIUSO          = 'chiuso';
    public const FALLITO         = 'fallito';
    public const DA_RIVERIFICARE = 'da_riverificare';
    public const BLOCCATO        = 'bloccato';
    public const SALTATO         = 'saltato';

    /** Stati da cui il tester puo' registrare un nuovo tentativo. */
    public const ESEGUIBILI = [self::DA_ESEGUIRE, self::DA_RIVERIFICARE, self::BLOCCATO, self::SALTATO];

    public const CASO_DA_INIZIARE = 'da_iniziare';
    public const CASO_IN_CORSO    = 'in_corso';
    public const CASO_CON_BUG     = 'con_bug';
    public const CASO_COMPLETATO  = 'completato';

    /**
     * @return array{piano: array<string, mixed>, casi: list<array<string, mixed>>, metriche: array<string, mixed>}|null
     */
    public function carica(int $pianoId, ?string $oggi = null): ?array
    {
        $db    = db_connect();
        $piano = $db->table('piani_test')->where('id', $pianoId)->get()->getRowArray();

        if ($piano === null) {
            return null;
        }

        $righeCasi = $db->table('piano_casi pc')
            ->select('pc.*, c.titolo, c.priorita, s.nome as suite_nome')
            ->join('casi_test c', 'c.id = pc.caso_id')
            ->join('suite s', 's.id = c.suite_id')
            ->where('pc.piano_id', $pianoId)
            ->orderBy('pc.ordine')
            ->get()
            ->getResultArray();

        $passaggi = $db->table('piano_casi pc')
            ->select('pc.id as piano_caso_id, p.id as passaggio_id, p.ordine, p.azione, p.risultato_atteso, p.stima_minuti')
            ->join('passaggi p', 'p.caso_id = pc.caso_id')
            ->where('pc.piano_id', $pianoId)
            ->orderBy('p.ordine')
            ->get()
            ->getResultArray();

        $esecuzioni = $db->table('esecuzioni_passaggio e')
            ->select('e.*')
            ->join('piano_casi pc', 'pc.id = e.piano_caso_id')
            ->where('pc.piano_id', $pianoId)
            ->orderBy('e.tentativo')
            ->get()
            ->getResultArray();

        $bug = $db->table('bug b')
            ->select('b.*, e.piano_caso_id, e.passaggio_id')
            ->join('esecuzioni_passaggio e', 'e.id = b.esecuzione_origine_id')
            ->join('piano_casi pc', 'pc.id = e.piano_caso_id')
            ->where('pc.piano_id', $pianoId)
            ->orderBy('b.id')
            ->get()
            ->getResultArray();

        $ultima  = [];
        $minuti  = [];
        $storico = [];
        foreach ($esecuzioni as $esecuzione) {
            $chiave = $esecuzione['piano_caso_id'] . ':' . $esecuzione['passaggio_id'];
            $ultima[$chiave]    = $esecuzione;
            $storico[$chiave][] = $esecuzione;

            // Tempo effettivo = somma di tutti i tentativi (riverifiche comprese).
            if ($esecuzione['minuti_impiegati'] !== null) {
                $minuti[$chiave] = ($minuti[$chiave] ?? 0) + (int) $esecuzione['minuti_impiegati'];
            }
        }
        $bugPerPassaggio = [];
        foreach ($bug as $riga) {
            $bugPerPassaggio[$riga['piano_caso_id'] . ':' . $riga['passaggio_id']] = $riga;
        }

        $passaggiPerCaso = [];
        foreach ($passaggi as $passaggio) {
            $chiave = $passaggio['piano_caso_id'] . ':' . $passaggio['passaggio_id'];

            $passaggio['ultima_esecuzione'] = $ultima[$chiave] ?? null;
            $passaggio['minuti_effettivi']  = $minuti[$chiave] ?? null;
            $passaggio['esecuzioni']        = $storico[$chiave] ?? [];
            $passaggio['bug']               = $bugPerPassaggio[$chiave] ?? null;
            $passaggio['stato']             = self::statoPassaggio($passaggio['ultima_esecuzione'], $passaggio['bug']);
            $passaggio['eseguibile']        = in_array($passaggio['stato'], self::ESEGUIBILI, true);

            $passaggiPerCaso[$passaggio['piano_caso_id']][] = $passaggio;
        }

        $tuttiPassaggi = [];
        foreach ($righeCasi as &$caso) {
            $caso['passaggi'] = $passaggiPerCaso[$caso['id']] ?? [];
            $caso['stato']    = self::statoCaso($caso['passaggi']);

            $conTempo = array_filter(array_column($caso['passaggi'], 'minuti_effettivi'), static fn ($m) => $m !== null);
            $caso['minuti_effettivi'] = $conTempo === [] ? null : array_sum($conTempo);
            array_push($tuttiPassaggi, ...$caso['passaggi']);
        }
        unset($caso);

        return [
            'piano'    => $piano,
            'casi'     => $righeCasi,
            'metriche' => self::calcolaMetriche($piano, $righeCasi, $tuttiPassaggi, $oggi ?? date('Y-m-d')),
        ];
    }

    /**
     * @param array<string, mixed>|null $ultimaEsecuzione
     * @param array<string, mixed>|null $bug
     */
    public static function statoPassaggio(?array $ultimaEsecuzione, ?array $bug): string
    {
        if ($bug !== null && $bug['stato'] === BugModel::STATO_RISOLTO) {
            return self::DA_RIVERIFICARE;
        }

        if ($bug !== null && in_array($bug['stato'], BugModel::STATI_APERTI, true)) {
            return self::FALLITO;
        }

        if ($ultimaEsecuzione === null) {
            return self::DA_ESEGUIRE;
        }

        return match ($ultimaEsecuzione['esito']) {
            'passato'  => self::CHIUSO,
            'bloccato' => self::BLOCCATO,
            'saltato'  => self::SALTATO,
            // Un fallimento senza bug collegato non dovrebbe esistere (il service lo
            // impedisce): se capita, resta comunque un passaggio non chiuso.
            default    => self::FALLITO,
        };
    }

    /**
     * @param list<array<string, mixed>> $passaggi
     */
    public static function statoCaso(array $passaggi): string
    {
        if ($passaggi === []) {
            return self::CASO_DA_INIZIARE;
        }

        $stati = array_column($passaggi, 'stato');

        if (array_intersect($stati, [self::FALLITO, self::DA_RIVERIFICARE]) !== []) {
            return self::CASO_CON_BUG;
        }

        if (count(array_unique($stati)) === 1 && $stati[0] === self::CHIUSO) {
            return self::CASO_COMPLETATO;
        }

        return array_diff($stati, [self::DA_ESEGUIRE]) !== [] ? self::CASO_IN_CORSO : self::CASO_DA_INIZIARE;
    }

    /**
     * Funzione pura (nessun accesso al database) cosi' si puo' testare con date fisse.
     *
     * @param array<string, mixed>       $piano
     * @param list<array<string, mixed>> $casi      righe piano_casi (stima_minuti)
     * @param list<array<string, mixed>> $passaggi  passaggi con 'stato', 'stima_minuti' e 'minuti_effettivi' gia' calcolati
     *
     * @return array<string, mixed>
     */
    public static function calcolaMetriche(array $piano, array $casi, array $passaggi, string $oggi): array
    {
        $conteggi = array_count_values(array_column($passaggi, 'stato'));
        $totale   = count($passaggi);
        $chiusi   = $conteggi[self::CHIUSO] ?? 0;
        $fermi    = ($conteggi[self::FALLITO] ?? 0) + ($conteggi[self::BLOCCATO] ?? 0);

        $quota      = (int) $piano['quota_riverifica'];
        $stimaBase  = array_sum(array_map(static fn ($c) => (int) $c['stima_minuti'], $casi));
        $stimaTotale = (int) round($stimaBase * (1 + $quota / 100));

        // Lo scostamento confronta solo i passaggi con un tempo registrato, altrimenti un
        // piano a meta' sembrerebbe "in anticipo" sul tempo solo perche' manca il dato.
        // Il tempo effettivo include le riverifiche, per questo la stima ha la quota extra.
        $misurati        = array_filter($passaggi, static fn ($p) => ($p['minuti_effettivi'] ?? null) !== null);
        $minutiEffettivi = $misurati === [] ? null : array_sum(array_map(static fn ($p) => (int) $p['minuti_effettivi'], $misurati));
        $stimaMisurata   = array_sum(array_map(static fn ($p) => (int) ($p['stima_minuti'] ?? 0), $misurati)) * (1 + $quota / 100);
        $scostamento     = ($minutiEffettivi !== null && $stimaMisurata > 0)
            ? round(($minutiEffettivi - $stimaMisurata) / $stimaMisurata * 100, 1)
            : null;

        $completo = $totale > 0 && $chiusi === $totale;
        $reale    = $totale > 0 ? round($chiusi / $totale * 100, 1) : 0.0;

        $inizio  = new DateTimeImmutable($piano['data_inizio']);
        $finePrev = new DateTimeImmutable($piano['data_fine_prevista']);
        $adesso  = new DateTimeImmutable($oggi);

        // Giorni di calendario, estremi inclusi: un piano di 5 giorni a fine primo giorno e' al 20%.
        $giorniTotali    = max(1, (int) $inizio->diff($finePrev)->format('%r%a') + 1);
        $giorniTrascorsi = $adesso < $inizio ? 0 : min($giorniTotali, (int) $inizio->diff($adesso)->format('%a') + 1);
        $atteso          = round($giorniTrascorsi / $giorniTotali * 100, 1);

        $chiuso = $piano['stato'] === 'chiuso';
        $ritardoPunti = $chiuso ? 0.0 : max(0.0, round($atteso - $reale, 1));

        $proiezione = null;
        if ($chiuso && $piano['data_fine_effettiva'] !== null) {
            $proiezione = $piano['data_fine_effettiva'];
        } elseif ($completo) {
            $proiezione = $oggi;
        } elseif ($chiusi > 0 && $giorniTrascorsi > 0) {
            $ritmo      = $chiusi / $giorniTrascorsi;
            $giorniMancanti = (int) ceil(($totale - $chiusi) / $ritmo);
            $proiezione = $adesso->modify("+{$giorniMancanti} days")->format('Y-m-d');
        }

        $scaduto = !$chiuso && !$completo && $adesso > $finePrev;

        return [
            'totale'              => $totale,
            'chiusi'              => $chiusi,
            'da_eseguire'         => $conteggi[self::DA_ESEGUIRE] ?? 0,
            'da_riverificare'     => $conteggi[self::DA_RIVERIFICARE] ?? 0,
            'fermi'               => $fermi,
            'saltati'             => $conteggi[self::SALTATO] ?? 0,
            'completo'            => $completo,
            'stima_minuti'        => $stimaTotale,
            'minuti_effettivi'    => $minutiEffettivi,
            'scostamento_pct'     => $scostamento,
            'avanzamento_reale'   => $reale,
            'avanzamento_atteso'  => $atteso,
            'giorni_totali'       => $giorniTotali,
            'giorni_trascorsi'    => $giorniTrascorsi,
            'ritardo_punti'       => $ritardoPunti,
            'ritardo_giorni'      => round($ritardoPunti / 100 * $giorniTotali, 1),
            'in_ritardo'          => $ritardoPunti > 0.0,
            'proiezione_fine'     => $proiezione,
            'a_rischio'           => $scaduto || (!$chiuso && !$completo && $proiezione !== null && $proiezione > $piano['data_fine_prevista']),
        ];
    }
}
