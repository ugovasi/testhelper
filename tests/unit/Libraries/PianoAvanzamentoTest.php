<?php

use App\Libraries\PianoAvanzamento;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Metriche di avanzamento: funzione pura, quindi testabile con date fisse.
 *
 * @internal
 */
final class PianoAvanzamentoTest extends CIUnitTestCase
{
    private function piano(array $override = []): array
    {
        return $override + [
            'data_inizio' => '2026-10-20', 'data_fine_prevista' => '2026-10-24', 'data_fine_effettiva' => null,
            'quota_riverifica' => 15, 'stato' => 'in_corso',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function passaggi(int $chiusi, int $totale, string $altri = 'da_eseguire'): array
    {
        return array_merge(
            array_fill(0, $chiusi, ['stato' => 'chiuso']),
            array_fill(0, $totale - $chiusi, ['stato' => $altri])
        );
    }

    private function casi(): array
    {
        return [
            ['stima_minuti' => 20, 'minuti_effettivi' => null],
            ['stima_minuti' => 15, 'minuti_effettivi' => null],
            ['stima_minuti' => 25, 'minuti_effettivi' => null],
        ];
    }

    public function testPrimoGiornoDiCinqueEAlVentiPercentoAtteso(): void
    {
        $m = PianoAvanzamento::calcolaMetriche($this->piano(), $this->casi(), $this->passaggi(6, 11), '2026-10-20');

        $this->assertSame(54.5, $m['avanzamento_reale']);
        $this->assertSame(20.0, $m['avanzamento_atteso']);
        $this->assertFalse($m['in_ritardo']);
        $this->assertSame(0.0, $m['ritardo_giorni']);
    }

    public function testStimaTotaleIncludeLaQuotaDiRiverifica(): void
    {
        $m = PianoAvanzamento::calcolaMetriche($this->piano(), $this->casi(), $this->passaggi(0, 11), '2026-10-20');

        $this->assertSame(69, $m['stima_minuti']); // 60 * 1.15
    }

    public function testRitardoInGiorniEProiezioneOltreLaFinePrevista(): void
    {
        // Giorno 4 di 5 (80% atteso) con solo 2 passaggi su 10 chiusi (20%).
        $m = PianoAvanzamento::calcolaMetriche($this->piano(), $this->casi(), $this->passaggi(2, 10), '2026-10-23');

        $this->assertSame(60.0, $m['ritardo_punti']);
        $this->assertSame(3.0, $m['ritardo_giorni']);
        $this->assertTrue($m['in_ritardo']);
        // Ritmo 0,5 passaggi/giorno: ne mancano 8 -> 16 giorni dal 23/10.
        $this->assertSame('2026-11-08', $m['proiezione_fine']);
        $this->assertTrue($m['a_rischio']);
    }

    public function testSenzaPassaggiChiusiLaProiezioneNonSiPuoCalcolare(): void
    {
        $m = PianoAvanzamento::calcolaMetriche($this->piano(), $this->casi(), $this->passaggi(0, 10), '2026-10-22');

        $this->assertNull($m['proiezione_fine']);
        $this->assertFalse($m['a_rischio'], 'a meta periodo e senza dati il piano non e ancora a rischio');
    }

    public function testPianoScadutoEIncompletoEARischio(): void
    {
        $m = PianoAvanzamento::calcolaMetriche($this->piano(), $this->casi(), $this->passaggi(0, 10), '2026-10-27');

        $this->assertTrue($m['a_rischio']);
        $this->assertSame(100.0, $m['avanzamento_atteso']);
    }

    public function testPrimaDellInizioLAvanzamentoAttesoEZero(): void
    {
        $m = PianoAvanzamento::calcolaMetriche($this->piano(), $this->casi(), $this->passaggi(0, 10), '2026-10-10');

        $this->assertSame(0.0, $m['avanzamento_atteso']);
        $this->assertFalse($m['in_ritardo']);
    }

    public function testPianoChiusoNonHaRitardoEUsaLaDataEffettiva(): void
    {
        $piano = $this->piano(['stato' => 'chiuso', 'data_fine_effettiva' => '2026-10-24']);
        $m     = PianoAvanzamento::calcolaMetriche($piano, $this->casi(), $this->passaggi(10, 10), '2026-12-01');

        $this->assertFalse($m['in_ritardo']);
        $this->assertFalse($m['a_rischio']);
        $this->assertSame('2026-10-24', $m['proiezione_fine']);
        $this->assertTrue($m['completo']);
    }

    public function testScostamentoConfrontaSoloIPassaggiConTempoRegistrato(): void
    {
        $passaggi = [
            ['stato' => 'chiuso', 'stima_minuti' => 20, 'minuti_effettivi' => 30],
            ['stato' => 'da_eseguire', 'stima_minuti' => 40, 'minuti_effettivi' => null],
        ];
        $m = PianoAvanzamento::calcolaMetriche($this->piano(), $this->casi(), $passaggi, '2026-10-20');

        $this->assertSame(30, $m['minuti_effettivi']);
        // 30 effettivi contro 20 * 1,15 = 23 stimati: +30,4%.
        $this->assertSame(30.4, $m['scostamento_pct']);
    }

    public function testPianoSenzaPassaggiNonDivideperZero(): void
    {
        $m = PianoAvanzamento::calcolaMetriche($this->piano(), [], [], '2026-10-20');

        $this->assertSame(0.0, $m['avanzamento_reale']);
        $this->assertFalse($m['completo']);
        $this->assertNull($m['scostamento_pct']);
    }

    public function testStatoDelPassaggioDaBugEdEsito(): void
    {
        $fallita = ['esito' => 'fallito'];

        $this->assertSame('da_eseguire', PianoAvanzamento::statoPassaggio(null, null));
        $this->assertSame('chiuso', PianoAvanzamento::statoPassaggio(['esito' => 'passato'], null));
        $this->assertSame('chiuso', PianoAvanzamento::statoPassaggio(['esito' => 'passato'], ['stato' => 'chiuso']));
        $this->assertSame('fallito', PianoAvanzamento::statoPassaggio($fallita, ['stato' => 'aperto']));
        $this->assertSame('fallito', PianoAvanzamento::statoPassaggio($fallita, ['stato' => 'riaperto']));
        $this->assertSame('da_riverificare', PianoAvanzamento::statoPassaggio($fallita, ['stato' => 'risolto']));
        $this->assertSame('bloccato', PianoAvanzamento::statoPassaggio(['esito' => 'bloccato'], null));
    }

    public function testStatoDelCasoDaiSuoiPassaggi(): void
    {
        $this->assertSame('da_iniziare', PianoAvanzamento::statoCaso([['stato' => 'da_eseguire']]));
        $this->assertSame('in_corso', PianoAvanzamento::statoCaso([['stato' => 'chiuso'], ['stato' => 'da_eseguire']]));
        $this->assertSame('con_bug', PianoAvanzamento::statoCaso([['stato' => 'chiuso'], ['stato' => 'fallito']]));
        $this->assertSame('con_bug', PianoAvanzamento::statoCaso([['stato' => 'da_riverificare']]));
        $this->assertSame('completato', PianoAvanzamento::statoCaso([['stato' => 'chiuso'], ['stato' => 'chiuso']]));
        $this->assertSame('in_corso', PianoAvanzamento::statoCaso([['stato' => 'chiuso'], ['stato' => 'bloccato']]));
    }
}
