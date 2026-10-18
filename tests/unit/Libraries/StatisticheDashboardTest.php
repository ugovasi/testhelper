<?php

use App\Libraries\EsecuzioneService;
use App\Libraries\PianoAvanzamento;
use App\Libraries\StatisticheDashboard;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Libraries\CreaFixture;

/**
 * @internal
 */
final class StatisticheDashboardTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CreaFixture;

    protected $refresh   = true;
    protected $namespace = 'App';

    public function testPassaggiAggregaIPianiApertiERaggruppaIDaFare(): void
    {
        $m = [
            ['totale' => 10, 'chiusi' => 4, 'da_riverificare' => 1, 'fermi' => 2, 'da_eseguire' => 2, 'saltati' => 1],
            ['totale' => 5, 'chiusi' => 5, 'da_riverificare' => 0, 'fermi' => 0, 'da_eseguire' => 0, 'saltati' => 0],
        ];

        $p = StatisticheDashboard::passaggi($m);

        $this->assertSame(15, $p['totale']);
        $this->assertSame(9, $p['chiusi']);
        $this->assertSame(1, $p['da_riverificare']);
        $this->assertSame(2, $p['fermi']);
        $this->assertSame(3, $p['da_fare'], 'mai eseguiti piu saltati');
        $this->assertSame(60.0, $p['percentuale']);
        $this->assertSame($p['totale'], $p['chiusi'] + $p['da_riverificare'] + $p['fermi'] + $p['da_fare'], 'i quattro gruppi coprono tutto');
    }

    public function testSenzaPianiLaPercentualeEZeroSenzaDividerePerZero(): void
    {
        $this->assertSame(0.0, StatisticheDashboard::passaggi([])['percentuale']);
    }

    public function testEsecuzioniPerGiornoHaUnaRigaPerOgniGiornoAncheVuoto(): void
    {
        $f = $this->creaPianoConUnCaso();
        $s = new EsecuzioneService();
        $s->registra($f['pianoCaso'], $f['passaggi'][0], 'passato', null, 3, null, $f['utente'], '2026-10-15 09:00:00');
        $s->registra($f['pianoCaso'], $f['passaggi'][1], 'bloccato', null, 2, null, $f['utente'], '2026-10-15 10:00:00');
        $s->registra($f['pianoCaso'], $f['passaggi'][2], 'fallito', null, 5, ['titolo' => 'Bug', 'gravita' => 'alta'], $f['utente'], '2026-10-17 11:00:00');
        // Fuori finestra: 14 giorni che finiscono il 17/10 iniziano il 04/10.
        $s->registra($f['pianoCaso'], $f['passaggi'][1], 'passato', null, 1, null, $f['utente'], '2026-10-16 08:00:00');

        $serie = StatisticheDashboard::esecuzioniPerGiorno('2026-10-17', 14);

        $this->assertCount(14, $serie);
        $this->assertSame('2026-10-04', $serie[0]['data']);
        $this->assertSame('2026-10-17', $serie[13]['data']);

        $per = array_column($serie, null, 'data');
        $this->assertSame(['totale' => 2, 'passati' => 1, 'falliti' => 0, 'altri' => 1], array_diff_key($per['2026-10-15'], ['data' => 1]));
        $this->assertSame(1, $per['2026-10-16']['totale']);
        $this->assertSame(1, $per['2026-10-17']['falliti']);
        $this->assertSame(0, $per['2026-10-10']['totale']);
        $this->assertSame(4, array_sum(array_column($serie, 'totale')));
    }

    public function testEsecuzioniFuoriFinestraNonVengonoContate(): void
    {
        $f = $this->creaPianoConUnCaso();
        (new EsecuzioneService())->registra($f['pianoCaso'], $f['passaggi'][0], 'passato', null, 1, null, $f['utente'], '2026-09-01 09:00:00');

        $this->assertSame(0, array_sum(array_column(StatisticheDashboard::esecuzioniPerGiorno('2026-10-17', 14), 'totale')));
    }

    public function testTestContaPianiEsecuzioniETempo(): void
    {
        $f = $this->creaPianoConUnCaso();
        $s = new EsecuzioneService();
        $s->registra($f['pianoCaso'], $f['passaggi'][0], 'passato', null, 3, null, $f['utente']);
        $s->registra($f['pianoCaso'], $f['passaggi'][1], 'passato', null, 4, null, $f['utente']);
        $s->registra($f['pianoCaso'], $f['passaggi'][2], 'saltato', null, null, null, $f['utente']);

        $t = StatisticheDashboard::test();

        $this->assertSame(1, $t['aperti']);
        $this->assertSame(0, $t['chiusi']);
        $this->assertSame(3, $t['esecuzioni']);
        $this->assertSame(7, $t['minuti'], 'il saltato non ha tempo');
    }

    public function testBugPerStatoGravitaEDurataMediaDiChiusura(): void
    {
        $f = $this->creaPianoConUnCaso();
        $s = new EsecuzioneService();
        $dati = static fn (string $g) => ['titolo' => 'Bug ' . $g, 'gravita' => $g];

        $s->registra($f['pianoCaso'], $f['passaggi'][0], 'fallito', null, 1, $dati('critica'), $f['utente']);
        $s->registra($f['pianoCaso'], $f['passaggi'][1], 'fallito', null, 1, $dati('bassa'), $f['utente']);
        $s->registra($f['pianoCaso'], $f['passaggi'][2], 'fallito', null, 1, $dati('bassa'), $f['utente']);

        $ids = array_column(db_connect()->table('bug')->orderBy('id')->get()->getResultArray(), 'id');
        $s->cambiaStatoBug((int) $ids[1], 'risolto', null, $f['utente']);
        $s->cambiaStatoBug((int) $ids[2], 'risolto', null, $f['utente']);
        $s->registra($f['pianoCaso'], $f['passaggi'][2], 'passato', null, 1, null, $f['utente']);
        // Chiuso dopo esattamente 2 giorni dalla segnalazione.
        db_connect()->table('bug')->where('id', $ids[2])->update(['created_at' => '2026-10-10 12:00:00', 'chiuso_il' => '2026-10-12 12:00:00']);

        $b = StatisticheDashboard::bug();

        $this->assertSame(1, $b['aperti'], 'solo il critico e ancora da correggere');
        $this->assertSame(1, $b['da_riverificare']);
        $this->assertSame(1, $b['chiusi']);
        $this->assertSame(['aperto' => 1, 'in_lavorazione' => 0, 'riaperto' => 0, 'risolto' => 1, 'chiuso' => 1], $b['per_stato']);
        $this->assertSame(['bassa' => 0, 'media' => 0, 'alta' => 0, 'critica' => 1], $b['aperti_per_gravita']);
        $this->assertSame(2.0, $b['giorni_medi_chiusura']);
    }

    public function testSenzaBugLaDurataMediaEAssente(): void
    {
        $b = StatisticheDashboard::bug();

        $this->assertNull($b['giorni_medi_chiusura']);
        $this->assertSame(0, $b['aperti']);
        $this->assertSame(array_sum($b['per_stato']), 0);
    }

    public function testLaSommaDeiGruppiTornaConLeMetricheDiUnPianoReale(): void
    {
        $f = $this->creaPianoConUnCaso();
        $s = new EsecuzioneService();
        $s->registra($f['pianoCaso'], $f['passaggi'][0], 'passato', null, 1, null, $f['utente']);
        $s->registra($f['pianoCaso'], $f['passaggi'][1], 'bloccato', null, 1, null, $f['utente']);
        $s->registra($f['pianoCaso'], $f['passaggi'][2], 'fallito', null, 1, ['titolo' => 'Bug', 'gravita' => 'bassa'], $f['utente']);

        $metriche = (new PianoAvanzamento())->carica($f['piano'])['metriche'];
        $p = StatisticheDashboard::passaggi([$metriche]);

        $this->assertEquals(['chiusi' => 1, 'fermi' => 2, 'da_fare' => 0, 'da_riverificare' => 0, 'totale' => 3], array_intersect_key($p, array_flip(['chiusi', 'fermi', 'da_fare', 'da_riverificare', 'totale'])));
    }
}
