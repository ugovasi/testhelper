<?php

use App\Libraries\EsecuzioneService;
use App\Libraries\PianoAvanzamento;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Libraries\CreaFixture;

/**
 * Riproduce il flusso dell'esempio d'uso: passaggio fallito -> bug -> fix -> riverifica
 * (anche fallita) -> chiusura del bug e del piano.
 *
 * @internal
 */
final class EsecuzioneServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CreaFixture;

    protected $refresh   = true;
    protected $namespace = 'App';

    private EsecuzioneService $service;

    /** @var array{piano: int, pianoCaso: int, passaggi: list<int>, utente: int} */
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EsecuzioneService();
        $this->f       = $this->creaPianoConUnCaso();
    }

    private function bugDati(): array
    {
        return ['titolo' => 'Sconto errato', 'descrizione' => 'Applica 1% invece di 10%', 'gravita' => 'alta', 'assegnato_a' => (string) $this->f['utente']];
    }

    private function registra(int $indicePassaggio, string $esito, ?array $bug = null, int|string|null $minuti = 4, ?string $quando = null): array
    {
        return $this->service->registra($this->f['pianoCaso'], $this->f['passaggi'][$indicePassaggio], $esito, null, $minuti, $bug, $this->f['utente'], $quando);
    }

    private function idEsecuzione(int $indicePassaggio, int $tentativo): int
    {
        return (int) db_connect()->table('esecuzioni_passaggio')
            ->where('passaggio_id', $this->f['passaggi'][$indicePassaggio])
            ->where('tentativo', $tentativo)
            ->get()->getRow()->id;
    }

    /** @return array<string, mixed> */
    private function stati(): array
    {
        $caricato = (new PianoAvanzamento())->carica($this->f['piano'], '2026-10-20');

        return [
            'passaggi' => array_column($caricato['casi'][0]['passaggi'], 'stato'),
            'caso'     => $caricato['casi'][0]['stato'],
            'metriche' => $caricato['metriche'],
        ];
    }

    public function testPassaggioPassatoSiChiudeEIlPianoDiventaInCorso(): void
    {
        $esito = $this->registra(0, 'passato');

        $this->assertTrue($esito['success']);
        $this->assertSame(['chiuso', 'da_eseguire', 'da_eseguire'], $this->stati()['passaggi']);
        $this->seeInDatabase('piani_test', ['id' => $this->f['piano'], 'stato' => 'in_corso']);
    }

    public function testFallimentoSenzaDatiDelBugVieneRifiutatoESenzaEffetti(): void
    {
        $esito = $this->registra(2, 'fallito');

        $this->assertFalse($esito['success']);
        $this->assertArrayHasKey('bug_titolo', $esito['errors']);
        $this->dontSeeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $this->f['passaggi'][2]]);
        $this->dontSeeInDatabase('bug', ['titolo' => 'Sconto errato']);
    }

    public function testFallimentoApreUnBugLegatoAlPassaggio(): void
    {
        $this->registra(0, 'passato');
        $this->registra(1, 'passato');
        $esito = $this->registra(2, 'fallito', $this->bugDati());

        $this->assertTrue($esito['success']);
        $this->seeInDatabase('bug', ['titolo' => 'Sconto errato', 'stato' => 'aperto', 'assegnato_a' => $this->f['utente']]);

        $stati = $this->stati();
        $this->assertSame(['chiuso', 'chiuso', 'fallito'], $stati['passaggi']);
        $this->assertSame('con_bug', $stati['caso']);
        $this->assertSame(1, $stati['metriche']['fermi']);
    }

    public function testUnPassaggioFallitoNonSiRieseguePrimaDellaCorrezione(): void
    {
        $this->registra(2, 'fallito', $this->bugDati());

        $esito = $this->registra(2, 'passato');

        $this->assertFalse($esito['success']);
        $this->assertSame(1, db_connect()->table('esecuzioni_passaggio')->where('passaggio_id', $this->f['passaggi'][2])->countAllResults());
    }

    public function testBugRisoltoPortaIlPassaggioDaRiverificareELaRiverificaChiudeIlBug(): void
    {
        $this->registra(2, 'fallito', $this->bugDati());
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;

        $this->assertTrue($this->service->cambiaStatoBug($bugId, 'in_lavorazione', null, $this->f['utente'])['success']);
        $this->assertTrue($this->service->cambiaStatoBug($bugId, 'risolto', 'formula corretta', $this->f['utente'])['success']);
        $this->assertSame('da_riverificare', $this->stati()['passaggi'][2]);

        $this->assertTrue($this->registra(2, 'passato')['success']);

        $this->seeInDatabase('bug', ['id' => $bugId, 'stato' => 'chiuso']);
        $this->seeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $this->f['passaggi'][2], 'tentativo' => 2, 'esito' => 'passato']);
        $this->assertSame('chiuso', $this->stati()['passaggi'][2]);
    }

    public function testRiverificaFallitaRiapreLoStessoBugEConservaLoStorico(): void
    {
        $this->registra(2, 'fallito', $this->bugDati());
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;
        $this->service->cambiaStatoBug($bugId, 'risolto', null, $this->f['utente']);

        $esito = $this->registra(2, 'fallito');

        $this->assertTrue($esito['success']);
        $this->assertSame(1, db_connect()->table('bug')->countAllResults(), 'nessun secondo bug');
        $this->seeInDatabase('bug', ['id' => $bugId, 'stato' => 'riaperto']);
        $this->assertSame(2, db_connect()->table('esecuzioni_passaggio')->where('passaggio_id', $this->f['passaggi'][2])->countAllResults());
        $this->assertSame('fallito', $this->stati()['passaggi'][2]);

        // Seconda correzione e seconda riverifica, stavolta superata (tentativo 3).
        $this->service->cambiaStatoBug($bugId, 'risolto', null, $this->f['utente']);
        $this->assertTrue($this->registra(2, 'passato')['success']);
        $this->seeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $this->f['passaggi'][2], 'tentativo' => 3]);
        $this->seeInDatabase('bug', ['id' => $bugId, 'stato' => 'chiuso']);
    }

    public function testLoSviluppatoreNonPuoSaltareDirettamenteAChiuso(): void
    {
        $this->registra(2, 'fallito', $this->bugDati());
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;

        $this->assertFalse($this->service->cambiaStatoBug($bugId, 'chiuso', null, $this->f['utente'])['success']);
        $this->seeInDatabase('bug', ['id' => $bugId, 'stato' => 'aperto']);
    }

    public function testUnBugNonAssegnatoViaPresoInCaricoDaChiLoLavora(): void
    {
        $this->registra(2, 'fallito', ['titolo' => 'Sconto errato', 'gravita' => 'media', 'assegnato_a' => '']);
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;

        $this->service->cambiaStatoBug($bugId, 'in_lavorazione', null, $this->f['utente']);

        $this->seeInDatabase('bug', ['id' => $bugId, 'assegnato_a' => $this->f['utente']]);
    }

    public function testBloccatoESaltatoRestanoEseguibiliEFermanoIlPiano(): void
    {
        $this->registra(0, 'bloccato');
        $this->registra(1, 'saltato');

        $stati = $this->stati();
        $this->assertSame(['bloccato', 'saltato', 'da_eseguire'], $stati['passaggi']);
        $this->assertSame('in_corso', $stati['caso']);

        $this->assertTrue($this->registra(0, 'passato')['success'], 'un passaggio bloccato si puo rieseguire');
    }

    public function testIlPianoSiChiudeSoloQuandoTuttiIPassaggiSonoChiusi(): void
    {
        $this->registra(0, 'passato');
        $this->assertFalse($this->service->chiudiPiano($this->f['piano'])['success']);

        $this->registra(1, 'passato');
        $this->registra(2, 'passato');
        $this->assertSame('completato', $this->stati()['caso']);

        $this->assertTrue($this->service->chiudiPiano($this->f['piano'])['success']);
        $this->seeInDatabase('piani_test', ['id' => $this->f['piano'], 'stato' => 'chiuso', 'data_fine_effettiva' => date('Y-m-d')]);
    }

    public function testSuUnPianoChiusoNonSiRegistranoAltreEsecuzioni(): void
    {
        foreach ([0, 1, 2] as $i) {
            $this->registra($i, 'passato');
        }
        $this->service->chiudiPiano($this->f['piano']);

        $this->assertFalse($this->registra(0, 'passato')['success']);
    }

    public function testIlTempoImpiegatoEObbligatorioTrannePerIlSalto(): void
    {
        foreach ([null, '', 'abc', '-2', '1.5'] as $minuti) {
            $esito = $this->registra(0, 'passato', null, $minuti);

            $this->assertFalse($esito['success'], 'minuti non validi: ' . var_export($minuti, true));
            $this->assertArrayHasKey('minuti_impiegati', $esito['errors']);
        }
        $this->dontSeeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $this->f['passaggi'][0]]);

        $this->assertTrue($this->registra(0, 'passato', null, 0)['success'], 'zero minuti e un valore valido');
        $this->assertTrue($this->registra(1, 'saltato', null, null)['success'], 'saltare non richiede il tempo');
        $this->seeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $this->f['passaggi'][1], 'minuti_impiegati' => null]);
    }

    public function testIlTempoSiSommaSuTuttiITentativiDelPassaggioEDelCaso(): void
    {
        // Passaggio 3: fallisce (5 min), viene corretto, riverifica passata (2 min).
        $this->registra(0, 'passato', null, 3);
        $this->registra(2, 'fallito', $this->bugDati(), 5);
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;
        $this->service->cambiaStatoBug($bugId, 'risolto', null, $this->f['utente']);
        $this->registra(2, 'passato', null, 2);

        $caricato = (new PianoAvanzamento())->carica($this->f['piano'], '2026-10-20');
        $passaggi = $caricato['casi'][0]['passaggi'];

        $this->assertSame(3, $passaggi[0]['minuti_effettivi']);
        $this->assertNull($passaggi[1]['minuti_effettivi'], 'nessun tempo se mai eseguito');
        $this->assertSame(7, $passaggi[2]['minuti_effettivi']);
        $this->assertSame(10, $caricato['casi'][0]['minuti_effettivi']);
        $this->assertSame(10, $caricato['metriche']['minuti_effettivi']);
    }

    public function testDataEOraDellEsecuzioneSiImpostanoSempre(): void
    {
        // Formato dei campi datetime-local, senza secondi.
        $this->assertTrue($this->registra(0, 'passato', null, 4, '2026-10-10T09:30')['success']);
        $this->seeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $this->f['passaggi'][0], 'eseguito_il' => '2026-10-10 09:30:00']);

        // Anche per "saltato" e per un esito che apre un bug.
        $this->assertTrue($this->registra(1, 'saltato', null, null, '2026-10-10 10:00:00')['success']);
        $this->assertTrue($this->registra(2, 'fallito', $this->bugDati(), 4, '2026-10-11T08:15')['success']);
        $this->seeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $this->f['passaggi'][2], 'eseguito_il' => '2026-10-11 08:15:00']);
    }

    public function testSenzaDataSiUsaLIstanteAttuale(): void
    {
        $prima = date('Y-m-d H:i:s', strtotime('-2 seconds'));
        $this->registra(0, 'passato', null, 4, '');
        $riga = db_connect()->table('esecuzioni_passaggio')->get()->getRow();

        $this->assertGreaterThanOrEqual($prima, $riga->eseguito_il);
        $this->assertLessThanOrEqual(date('Y-m-d H:i:s', strtotime('+2 seconds')), $riga->eseguito_il);
    }

    public function testDataNonValidaOFuturaVieneRifiutata(): void
    {
        foreach (['ieri', '2026-13-45T10:00', '2026-02-30 10:00', date('Y-m-d H:i:s', strtotime('+1 day'))] as $valore) {
            $esito = $this->registra(0, 'passato', null, 4, $valore);

            $this->assertFalse($esito['success'], $valore);
            $this->assertArrayHasKey('eseguito_il', $esito['errors']);
        }
        $this->dontSeeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $this->f['passaggi'][0]]);
    }

    public function testUnTentativoNonPuoPrecedereQuelloPrecedente(): void
    {
        $this->registra(2, 'fallito', $this->bugDati(), 4, '2026-10-10 10:00:00');
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;
        $this->service->cambiaStatoBug($bugId, 'risolto', null, $this->f['utente']);

        $this->assertFalse($this->registra(2, 'passato', null, 4, '2026-10-10 09:00:00')['success']);
        $this->assertTrue($this->registra(2, 'passato', null, 4, '2026-10-10 11:00:00')['success']);
        $this->seeInDatabase('bug', ['id' => $bugId, 'stato' => 'chiuso', 'chiuso_il' => '2026-10-10 11:00:00']);
    }

    public function testModificaCorreggeTempoNoteEDataSenzaToccareLEsito(): void
    {
        $this->registra(0, 'passato', null, 4, '2026-10-10 09:00:00');
        $id = $this->idEsecuzione(0, 1);

        $esito = $this->service->modifica($id, 'rifatto con calma', 9, '2026-10-10T09:45');

        $this->assertTrue($esito['success']);
        $this->seeInDatabase('esecuzioni_passaggio', [
            'id' => $id, 'esito' => 'passato', 'minuti_impiegati' => 9, 'note' => 'rifatto con calma', 'eseguito_il' => '2026-10-10 09:45:00',
        ]);
        $this->assertSame('chiuso', $this->stati()['passaggi'][0]);
        $this->assertSame(9, $this->stati()['metriche']['minuti_effettivi']);
    }

    public function testModificaRispettaITentativiAdiacenti(): void
    {
        $this->registra(2, 'fallito', $this->bugDati(), 4, '2026-10-10 10:00:00');
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;
        $this->service->cambiaStatoBug($bugId, 'risolto', null, $this->f['utente']);
        $this->registra(2, 'passato', null, 4, '2026-10-10 12:00:00');

        $primo    = $this->idEsecuzione(2, 1);
        $secondo  = $this->idEsecuzione(2, 2);

        $this->assertFalse($this->service->modifica($primo, null, 4, '2026-10-10 13:00:00')['success'], 'il primo non puo superare il secondo');
        $this->assertFalse($this->service->modifica($secondo, null, 4, '2026-10-10 09:00:00')['success'], 'il secondo non puo precedere il primo');
        $this->assertTrue($this->service->modifica($primo, null, 4, '2026-10-10 11:30:00')['success']);

        // La data di chiusura del bug segue la riverifica che l'ha chiuso.
        $this->assertTrue($this->service->modifica($secondo, null, 4, '2026-10-10 14:00:00')['success']);
        $this->seeInDatabase('bug', ['id' => $bugId, 'chiuso_il' => '2026-10-10 14:00:00']);
    }

    public function testModificaRichiedeTempoEDataValidiETranneSaltatoNonAccettaVuoti(): void
    {
        $this->registra(0, 'passato', null, 4, '2026-10-10 09:00:00');
        $this->registra(1, 'saltato', null, null, '2026-10-10 09:05:00');
        $passato = $this->idEsecuzione(0, 1);
        $saltato = $this->idEsecuzione(1, 1);

        $this->assertArrayHasKey('minuti_impiegati', $this->service->modifica($passato, null, '', '2026-10-10 09:00')['errors']);
        $this->assertArrayHasKey('eseguito_il', $this->service->modifica($passato, null, 4, '')['errors']);
        $this->assertArrayHasKey('eseguito_il', $this->service->modifica($passato, null, 4, 'boh')['errors']);
        $this->assertTrue($this->service->modifica($saltato, 'motivo', null, '2026-10-10 09:06')['success']);
        $this->seeInDatabase('esecuzioni_passaggio', ['id' => $saltato, 'minuti_impiegati' => null, 'note' => 'motivo']);
    }

    public function testSuUnPianoChiusoLeEsecuzioniNonSiModificano(): void
    {
        foreach ([0, 1, 2] as $i) {
            $this->registra($i, 'passato', null, 4, '2026-10-10 09:0' . $i . ':00');
        }
        $this->service->chiudiPiano($this->f['piano']);

        $this->assertFalse($this->service->modifica($this->idEsecuzione(0, 1), null, 5, '2026-10-10 09:00')['success']);
        $this->assertFalse($this->service->modifica(999999, null, 5, '2026-10-10 09:00')['success']);
    }

    public function testEsitoOPassaggioNonValidiVengonoRifiutati(): void
    {
        $this->assertFalse($this->registra(0, 'forse')['success']);
        $this->assertFalse($this->service->registra($this->f['pianoCaso'], 999999, 'passato', null, 3, null, 1)['success']);
        $this->assertFalse($this->service->registra(999999, $this->f['passaggi'][0], 'passato', null, 3, null, 1)['success']);
    }
}
