<?php

namespace App\Libraries;

use App\Models\BugModel;

/**
 * Numeri della dashboard: stato dei passaggi nei piani aperti, esecuzioni per giorno e
 * statistiche sui bug. Le query usano solo il query builder e aggregano in PHP dove la
 * funzione SQL cambierebbe tra SQLite e MySQL (date, medie).
 */
class StatisticheDashboard
{
    public const GIORNI_ESECUZIONI = 14;

    /**
     * Stato aggregato dei passaggi di tutti i piani aperti.
     *
     * @param list<array<string, mixed>> $metrichePianiAperti metriche (PianoAvanzamento::calcolaMetriche) dei piani aperti
     *
     * @return array<string, int|float>
     */
    public static function passaggi(array $metrichePianiAperti): array
    {
        $somma = static fn (string $chiave) => array_sum(array_column($metrichePianiAperti, $chiave));

        $totale = (int) $somma('totale');
        $chiusi = (int) $somma('chiusi');

        return [
            'totale'          => $totale,
            'chiusi'          => $chiusi,
            'da_riverificare' => (int) $somma('da_riverificare'),
            'fermi'           => (int) $somma('fermi'),
            // Saltati e mai eseguiti sono entrambi "ancora da fare".
            'da_fare'         => (int) $somma('da_eseguire') + (int) $somma('saltati'),
            'percentuale'     => $totale > 0 ? round($chiusi / $totale * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array{aperti: int, chiusi: int, esecuzioni: int, minuti: int}
     */
    public static function test(): array
    {
        $db = db_connect();

        return [
            'aperti'     => $db->table('piani_test')->where('stato !=', 'chiuso')->countAllResults(),
            'chiusi'     => $db->table('piani_test')->where('stato', 'chiuso')->countAllResults(),
            'esecuzioni' => $db->table('esecuzioni_passaggio')->countAllResults(),
            'minuti'     => (int) ($db->table('esecuzioni_passaggio')->selectSum('minuti_impiegati', 'totale')->get()->getRow()->totale ?? 0),
        ];
    }

    /**
     * Esecuzioni registrate negli ultimi $giorni giorni (oggi compreso), un elemento per
     * giorno anche se vuoto, dal piu' vecchio al piu' recente.
     *
     * @return list<array{data: string, totale: int, passati: int, falliti: int, altri: int}>
     */
    public static function esecuzioniPerGiorno(?string $oggi = null, int $giorni = self::GIORNI_ESECUZIONI): array
    {
        $oggi   = new \DateTimeImmutable($oggi ?? date('Y-m-d'));
        $inizio = $oggi->modify('-' . ($giorni - 1) . ' days');

        $serie = [];
        for ($i = 0; $i < $giorni; $i++) {
            $serie[$inizio->modify("+{$i} days")->format('Y-m-d')] = ['totale' => 0, 'passati' => 0, 'falliti' => 0, 'altri' => 0];
        }

        $righe = db_connect()->table('esecuzioni_passaggio')
            ->select('eseguito_il, esito')
            ->where('eseguito_il >=', $inizio->format('Y-m-d') . ' 00:00:00')
            ->where('eseguito_il <=', $oggi->format('Y-m-d') . ' 23:59:59')
            ->get()->getResultArray();

        foreach ($righe as $riga) {
            $giorno = substr($riga['eseguito_il'], 0, 10);
            if (!isset($serie[$giorno])) {
                continue;
            }

            $serie[$giorno]['totale']++;
            $chiave = match ($riga['esito']) {
                'passato' => 'passati',
                'fallito' => 'falliti',
                default   => 'altri',
            };
            $serie[$giorno][$chiave]++;
        }

        $risultato = [];
        foreach ($serie as $data => $valori) {
            $risultato[] = ['data' => $data] + $valori;
        }

        return $risultato;
    }

    /**
     * @return array{
     *     aperti: int, da_riverificare: int, chiusi: int, giorni_medi_chiusura: float|null,
     *     per_stato: array<string, int>, aperti_per_gravita: array<string, int>
     * }
     */
    public static function bug(): array
    {
        $db = db_connect();

        $perStato = array_fill_keys(
            [BugModel::STATO_APERTO, BugModel::STATO_IN_LAVORAZIONE, BugModel::STATO_RIAPERTO, BugModel::STATO_RISOLTO, BugModel::STATO_CHIUSO],
            0
        );
        foreach ($db->table('bug')->select('stato, count(*) as n')->groupBy('stato')->get()->getResultArray() as $riga) {
            $perStato[$riga['stato']] = (int) $riga['n'];
        }

        $perGravita = array_fill_keys(BugModel::GRAVITA, 0);
        $aperti = $db->table('bug')->select('gravita, count(*) as n')->whereIn('stato', BugModel::STATI_APERTI)->groupBy('gravita')->get()->getResultArray();
        foreach ($aperti as $riga) {
            $perGravita[$riga['gravita']] = (int) $riga['n'];
        }

        $durate = [];
        $chiusi = $db->table('bug')->select('created_at, chiuso_il')->where('stato', BugModel::STATO_CHIUSO)->where('chiuso_il IS NOT NULL')->get()->getResultArray();
        foreach ($chiusi as $riga) {
            $durate[] = max(0, strtotime($riga['chiuso_il']) - strtotime($riga['created_at']));
        }

        return [
            'aperti'               => array_sum(array_intersect_key($perStato, array_flip(BugModel::STATI_APERTI))),
            'da_riverificare'      => $perStato[BugModel::STATO_RISOLTO],
            'chiusi'               => $perStato[BugModel::STATO_CHIUSO],
            'giorni_medi_chiusura' => $durate === [] ? null : round(array_sum($durate) / count($durate) / 86400, 1),
            'per_stato'            => $perStato,
            'aperti_per_gravita'   => $perGravita,
        ];
    }
}
