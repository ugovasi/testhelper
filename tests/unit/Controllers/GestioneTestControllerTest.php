<?php

use App\Database\Seeds\GestioneTestSeeder;
use App\Libraries\PianoAvanzamento;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\Libraries\CreaFixture;

/**
 * Flussi HTTP della gestione piani di test: CRUD della libreria, creazione del piano,
 * esecuzione, gestione bug lato sviluppatore e permessi.
 *
 * @internal
 */
final class GestioneTestControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use CreaFixture;

    protected $refresh   = true;
    protected $namespace = 'App';

    private function csrf(): array
    {
        return [Services::security()->getTokenName() => csrf_hash()];
    }

    private function ajax(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest', 'X-CSRF-TOKEN' => csrf_hash()];
    }

    /** @param string[] $permessi */
    private function come(int $utenteId, array $permessi)
    {
        return $this->withSession([
            'logged_in' => true, 'user_id' => $utenteId, 'nome' => 'Marta', 'email' => 'marta@example.com', 'permessi' => $permessi,
        ])->withHeaders($this->ajax());
    }

    private const TUTTI = [
        'prodotti.view', 'prodotti.create', 'prodotti.edit', 'prodotti.delete',
        'casi.view', 'casi.create', 'casi.edit', 'casi.delete',
        'piani.view', 'piani.create', 'piani.edit', 'piani.delete',
        'esecuzioni.view', 'esecuzioni.create', 'bug.view', 'bug.update',
    ];

    public function testLibreriaProdottoSuiteCasoConPassaggi(): void
    {
        $f = $this->creaPianoConUnCaso();

        $this->come($f['utente'], self::TUTTI)->post('admin/prodotti/store', ['nome' => 'AltroProdotto', 'attivo' => '1'] + $this->csrf())
            ->assertJSONFragment(['success' => true]);
        $prodottoId = (int) db_connect()->table('prodotti')->where('nome', 'AltroProdotto')->get()->getRow()->id;
        $this->seeInDatabase('prodotti', ['nome' => 'AltroProdotto', 'created_by' => $f['utente'], 'attivo' => 1]);

        $this->come($f['utente'], self::TUTTI)->post('admin/suite/store', ['prodotto_id' => $prodottoId, 'nome' => 'Login'] + $this->csrf())
            ->assertJSONFragment(['success' => true]);
        $suiteId = (int) db_connect()->table('suite')->where('nome', 'Login')->get()->getRow()->id;

        $this->come($f['utente'], self::TUTTI)->post('admin/casi/store', [
            'suite_id' => $suiteId, 'titolo' => 'Login valido', 'priorita' => 'alta',
            'passaggi' => [
                ['id' => '', 'azione' => 'Apri la pagina', 'risultato_atteso' => 'Form visibile', 'stima_minuti' => '3'],
                ['id' => '', 'azione' => 'Invia le credenziali', 'risultato_atteso' => '', 'stima_minuti' => '7'],
            ],
        ] + $this->csrf())->assertJSONFragment(['success' => true]);

        $casoId = (int) db_connect()->table('casi_test')->where('titolo', 'Login valido')->get()->getRow()->id;
        $passaggi = db_connect()->table('passaggi')->where('caso_id', $casoId)->orderBy('ordine')->get()->getResultArray();
        $this->assertSame(['Apri la pagina', 'Invia le credenziali'], array_column($passaggi, 'azione'));
        $this->assertNull($passaggi[1]['risultato_atteso']);
        $this->assertSame([3, 7], array_map('intval', array_column($passaggi, 'stima_minuti')));
        $this->seeInDatabase('casi_test', ['id' => $casoId, 'stima_minuti' => 10]);
    }

    public function testCasoSenzaPassaggiViene422(): void
    {
        $f = $this->creaPianoConUnCaso();
        $suiteId = (int) db_connect()->table('suite')->get()->getRow()->id;

        $result = $this->come($f['utente'], self::TUTTI)->post('admin/casi/store', [
            'suite_id' => $suiteId, 'titolo' => 'Vuoto', 'priorita' => 'media',
        ] + $this->csrf());

        $this->assertSame(422, $result->response()->getStatusCode());
        $result->assertJSONFragment(['errors' => ['passaggi' => 'Aggiungi almeno un passaggio.']]);
        $this->dontSeeInDatabase('casi_test', ['titolo' => 'Vuoto']);
    }

    public function testModificaDelCasoMantieneGliIdDeiPassaggiEsistenti(): void
    {
        $f = $this->creaPianoConUnCaso();
        $casoId = (int) db_connect()->table('casi_test')->get()->getRow()->id;

        $this->come($f['utente'], self::TUTTI)->post("admin/casi/update/{$casoId}", [
            'suite_id' => db_connect()->table('suite')->get()->getRow()->id, 'titolo' => 'Applicazione coupon', 'priorita' => 'media',
            'passaggi' => [
                ['id' => $f['passaggi'][0], 'azione' => 'Aggiungi prodotto (modificato)', 'stima_minuti' => '5'],
                ['id' => $f['passaggi'][1], 'azione' => 'Inserisci coupon', 'stima_minuti' => '5'],
                ['id' => $f['passaggi'][2], 'azione' => 'Verifica totale', 'stima_minuti' => '5'],
                ['id' => '', 'azione' => 'Nuovo quarto passaggio', 'stima_minuti' => '10'],
            ],
        ] + $this->csrf())->assertJSONFragment(['success' => true]);

        $this->seeInDatabase('passaggi', ['id' => $f['passaggi'][0], 'azione' => 'Aggiungi prodotto (modificato)']);
        $this->assertSame(4, db_connect()->table('passaggi')->where('caso_id', $casoId)->countAllResults());
        $this->seeInDatabase('casi_test', ['id' => $casoId, 'stima_minuti' => 25]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postCasoConStima(array $f, mixed $stima): array
    {
        return [
            'suite_id' => db_connect()->table('suite')->get()->getRow()->id, 'titolo' => 'Nuovo caso', 'priorita' => 'media',
            'passaggi' => [['id' => '', 'azione' => 'Passo', 'stima_minuti' => $stima]],
        ] + $this->csrf();
    }

    public function testLaStimaDelPassaggioEObbligatoria(): void
    {
        $f = $this->creaPianoConUnCaso();

        foreach (['', '0', '-3', 'abc', '2.5'] as $stima) {
            $result = $this->come($f['utente'], self::TUTTI)->post('admin/casi/store', $this->postCasoConStima($f, $stima));

            $this->assertSame(422, $result->response()->getStatusCode(), "stima \"{$stima}\" doveva essere rifiutata");
        }
        $this->dontSeeInDatabase('casi_test', ['titolo' => 'Nuovo caso']);

        $this->come($f['utente'], self::TUTTI)->post('admin/casi/store', $this->postCasoConStima($f, '4'))
            ->assertJSONFragment(['success' => true]);
    }

    public function testNonSiEliminaUnPassaggioGiaEseguito(): void
    {
        $f = $this->creaPianoConUnCaso();
        $casoId = (int) db_connect()->table('casi_test')->get()->getRow()->id;
        (new App\Libraries\EsecuzioneService())->registra($f['pianoCaso'], $f['passaggi'][2], 'passato', null, 1, null, $f['utente']);

        $result = $this->come($f['utente'], self::TUTTI)->post("admin/casi/update/{$casoId}", [
            'suite_id' => db_connect()->table('suite')->get()->getRow()->id, 'titolo' => 'Applicazione coupon',  'priorita' => 'media',
            'passaggi' => [
                ['id' => $f['passaggi'][0], 'azione' => 'a', 'stima_minuti' => '5'],
                ['id' => $f['passaggi'][1], 'azione' => 'b', 'stima_minuti' => '5'],
            ],
        ] + $this->csrf());

        $this->assertSame(422, $result->response()->getStatusCode());
        $this->assertSame(3, db_connect()->table('passaggi')->where('caso_id', $casoId)->countAllResults());
    }

    public function testNonSiEliminaUnCasoInclusoInUnPiano(): void
    {
        $f = $this->creaPianoConUnCaso();
        $casoId = (int) db_connect()->table('casi_test')->get()->getRow()->id;

        $result = $this->come($f['utente'], self::TUTTI)->delete("admin/casi/delete/{$casoId}");

        $this->assertSame(422, $result->response()->getStatusCode());
        $this->seeInDatabase('casi_test', ['id' => $casoId]);
    }

    public function testCreazionePianoCopiaLaStimaDelCasoEValidaLeDate(): void
    {
        $f = $this->creaPianoConUnCaso();
        $versioneId = (int) db_connect()->table('versioni')->get()->getRow()->id;
        $casoId     = (int) db_connect()->table('casi_test')->get()->getRow()->id;
        $base = ['versione_id' => $versioneId, 'nome' => 'Smoke', 'quota_riverifica' => 10, 'casi' => [$casoId]];

        $errore = $this->come($f['utente'], self::TUTTI)->post('admin/piani/store', $base + ['data_inizio' => '2026-10-24', 'data_fine_prevista' => '2026-10-20'] + $this->csrf());
        $this->assertSame(422, $errore->response()->getStatusCode());

        $this->come($f['utente'], self::TUTTI)->post('admin/piani/store', $base + ['data_inizio' => '2026-10-20', 'data_fine_prevista' => '2026-10-22'] + $this->csrf())
            ->assertJSONFragment(['success' => true]);

        $pianoId = (int) db_connect()->table('piani_test')->where('nome', 'Smoke')->get()->getRow()->id;
        $this->seeInDatabase('piano_casi', ['piano_id' => $pianoId, 'caso_id' => $casoId, 'stima_minuti' => 15]);
        $this->seeInDatabase('piani_test', ['id' => $pianoId, 'stato' => 'bozza']);
    }

    public function testNonSiRimuoveDalPianoUnCasoGiaEseguito(): void
    {
        $f = $this->creaPianoConUnCaso();
        (new App\Libraries\EsecuzioneService())->registra($f['pianoCaso'], $f['passaggi'][0], 'passato', null, 1, null, $f['utente']);
        $piano = db_connect()->table('piani_test')->get()->getRowArray();

        $result = $this->come($f['utente'], self::TUTTI)->post("admin/piani/update/{$f['piano']}", [
            'versione_id' => $piano['versione_id'], 'nome' => $piano['nome'], 'quota_riverifica' => 15,
            'data_inizio' => '2026-10-20', 'data_fine_prevista' => '2026-10-24', 'casi' => [],
        ] + $this->csrf());

        $this->assertSame(422, $result->response()->getStatusCode());
        $this->seeInDatabase('piano_casi', ['id' => $f['pianoCaso']]);
    }

    public function testFlussoHttpFallimentoBugFixERiverifica(): void
    {
        $f = $this->creaPianoConUnCaso();
        $tester = self::TUTTI;
        $dev    = ['piani.view', 'esecuzioni.view', 'bug.view', 'bug.update'];

        $this->come($f['utente'], $tester)->post('admin/esecuzioni/registra', [
            'piano_caso_id' => $f['pianoCaso'], 'passaggio_id' => $f['passaggi'][2], 'esito' => 'fallito', 'note' => 'sconto 1%', 'minuti_impiegati' => '6',
            'bug_titolo' => 'Sconto errato', 'bug_gravita' => 'alta', 'bug_assegnato_a' => $f['utente'],
        ] + $this->csrf())->assertJSONFragment(['success' => true]);
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;

        $this->come($f['utente'], $dev)->post("admin/bug/update/{$bugId}", ['stato' => 'risolto', 'note_risoluzione' => 'fix formula'] + $this->csrf())
            ->assertJSONFragment(['success' => true]);
        $this->seeInDatabase('bug', ['id' => $bugId, 'stato' => 'risolto', 'note_risoluzione' => 'fix formula']);

        $riverifiche = $this->come($f['utente'], $tester)->get('admin/esecuzioni/riverifiche?tutti=1');
        $riverifiche->assertOK();
        $riverifiche->assertSee('Sconto errato');

        $this->come($f['utente'], $tester)->post('admin/esecuzioni/registra', [
            'piano_caso_id' => $f['pianoCaso'], 'passaggio_id' => $f['passaggi'][2], 'esito' => 'passato', 'minuti_impiegati' => '2',
        ] + $this->csrf())->assertJSONFragment(['success' => true]);
        $this->seeInDatabase('bug', ['id' => $bugId, 'stato' => 'chiuso']);
        $this->seeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $f['passaggi'][2], 'tentativo' => 2, 'minuti_impiegati' => 2]);
    }

    public function testModificaEsecuzioneViaHttpEPermessi(): void
    {
        $f = $this->creaPianoConUnCaso();
        (new App\Libraries\EsecuzioneService())->registra($f['pianoCaso'], $f['passaggi'][0], 'passato', null, 4, null, $f['utente'], '2026-10-10 09:00:00');
        $id = (int) db_connect()->table('esecuzioni_passaggio')->get()->getRow()->id;

        $this->come($f['utente'], ['esecuzioni.view', 'piani.view'])
            ->post("admin/esecuzioni/modifica/{$id}", ['minuti_impiegati' => '8', 'eseguito_il' => '2026-10-10T09:20'] + $this->csrf())
            ->assertStatus(403);

        $this->come($f['utente'], self::TUTTI)
            ->post("admin/esecuzioni/modifica/{$id}", ['minuti_impiegati' => '8', 'note' => 'ok', 'eseguito_il' => '2026-10-10T09:20'] + $this->csrf())
            ->assertJSONFragment(['success' => true]);
        $this->seeInDatabase('esecuzioni_passaggio', ['id' => $id, 'minuti_impiegati' => 8, 'eseguito_il' => '2026-10-10 09:20:00']);
        $this->seeInDatabase('log_attivita', ['modulo' => 'esecuzioni', 'azione' => 'update', 'esito' => 1]);

        $errore = $this->come($f['utente'], self::TUTTI)
            ->post("admin/esecuzioni/modifica/{$id}", ['minuti_impiegati' => '8', 'eseguito_il' => 'xx'] + $this->csrf());
        $this->assertSame(422, $errore->response()->getStatusCode());
    }

    /**
     * Crea un bug con un allegato vero nella cartella reale dei caricamenti (da ripulire).
     *
     * @return array{bug: int, allegato: int, file: string, cartella: string}
     */
    private function bugConAllegato(array $f, string $nomeFile, string $mime, string $contenuto): array
    {
        (new App\Libraries\EsecuzioneService())->registra($f['pianoCaso'], $f['passaggi'][0], 'fallito', null, 1, ['titolo' => 'Bug demo', 'gravita' => 'bassa'], $f['utente']);
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;

        $cartella = WRITEPATH . 'uploads/bug/' . $bugId;
        @mkdir($cartella, 0750, true);
        file_put_contents($cartella . '/' . $nomeFile, $contenuto);

        db_connect()->table('bug_allegati')->insert([
            'bug_id' => $bugId, 'nome_originale' => 'prova "uno".txt', 'nome_file' => $nomeFile, 'mime' => $mime,
            'dimensione' => strlen($contenuto), 'caricato_da' => $f['utente'], 'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['bug' => $bugId, 'allegato' => (int) db_connect()->insertID(), 'file' => $cartella . '/' . $nomeFile, 'cartella' => $cartella];
    }

    public function testIlDownloadDegliAllegatiHaIntestazioniDiSicurezzaERichiedeIPermessi(): void
    {
        $f = $this->creaPianoConUnCaso();
        $a = $this->bugConAllegato($f, 'abc123.txt', 'text/plain', 'log di prova');

        try {
            $ok = $this->come($f['utente'], ['bug.view'])->get("admin/bug/allegato/{$a['allegato']}");
            $ok->assertOK();
            $ok->assertSee('log di prova');
            $this->assertSame('nosniff', $ok->response()->getHeaderLine('X-Content-Type-Options'));
            $this->assertStringContainsString('sandbox', $ok->response()->getHeaderLine('Content-Security-Policy'));
            $this->assertStringStartsWith('attachment;', $ok->response()->getHeaderLine('Content-Disposition'));
            $this->assertStringNotContainsString('"uno"', $ok->response()->getHeaderLine('Content-Disposition'), 'le virgolette del nome sono neutralizzate');

            $this->assertSame(403, $this->come($f['utente'], ['piani.view'])->get("admin/bug/allegato/{$a['allegato']}")->response()->getStatusCode());
            $this->assertSame(404, $this->come($f['utente'], ['bug.view'])->get('admin/bug/allegato/999999')->response()->getStatusCode());
        } finally {
            @unlink($a['file']);
            @rmdir($a['cartella']);
        }
    }

    public function testLeImmaginiSiMostranoInLineaGliAltriFileSiScaricano(): void
    {
        $f = $this->creaPianoConUnCaso();
        $a = $this->bugConAllegato($f, 'img456.png', 'image/png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        try {
            $r = $this->come($f['utente'], ['bug.view'])->get("admin/bug/allegato/{$a['allegato']}");

            $this->assertSame('image/png', $r->response()->getHeaderLine('Content-Type'));
            $this->assertStringStartsWith('inline;', $r->response()->getHeaderLine('Content-Disposition'));
        } finally {
            @unlink($a['file']);
            @rmdir($a['cartella']);
        }
    }

    public function testLEliminazioneDiUnAllegatoVaPerPermessoEPulisceIlDisco(): void
    {
        $f = $this->creaPianoConUnCaso();
        $a = $this->bugConAllegato($f, 'del789.txt', 'text/plain', 'x');

        try {
            // Un semplice lettore non puo' togliere allegati...
            $this->assertSame(403, $this->come($f['utente'], ['bug.view'])->post("admin/bug/elimina-allegato/{$a['allegato']}", $this->csrf())->response()->getStatusCode());
            $this->assertFileExists($a['file']);

            // ...lo sviluppatore (bug.update) e il tester (esecuzioni.create) si'.
            $this->come($f['utente'], ['bug.view', 'esecuzioni.create'])->post("admin/bug/elimina-allegato/{$a['allegato']}", $this->csrf())
                ->assertJSONFragment(['success' => true]);
            $this->assertFileDoesNotExist($a['file']);
            $this->dontSeeInDatabase('bug_allegati', ['id' => $a['allegato']]);
            $this->seeInDatabase('log_attivita', ['modulo' => 'bug_allegati', 'azione' => 'delete', 'esito' => 1]);
        } finally {
            @unlink($a['file']);
            @rmdir($a['cartella']);
        }
    }

    public function testAllegaSenzaFileOSenzaPermessoVieneRifiutato(): void
    {
        $f = $this->creaPianoConUnCaso();
        $a = $this->bugConAllegato($f, 'ccc.txt', 'text/plain', 'x');

        try {
            $senzaFile = $this->come($f['utente'], ['bug.view', 'bug.update'])->post("admin/bug/allega/{$a['bug']}", $this->csrf());
            $this->assertSame(422, $senzaFile->response()->getStatusCode());

            $lettore = $this->come($f['utente'], ['bug.view'])->post("admin/bug/allega/{$a['bug']}", $this->csrf());
            $this->assertSame(403, $lettore->response()->getStatusCode());

            $this->assertSame(404, $this->come($f['utente'], ['bug.view', 'bug.update'])->post('admin/bug/allega/999999', $this->csrf())->response()->getStatusCode());
        } finally {
            @unlink($a['file']);
            @rmdir($a['cartella']);
        }
    }

    public function testIlDettaglioDelBugMostraGliAllegatiEIControlliSecondoIPermessi(): void
    {
        $f = $this->creaPianoConUnCaso();
        $a = $this->bugConAllegato($f, 'ddd.txt', 'text/plain', 'x');

        try {
            $dev = json_decode($this->come($f['utente'], ['bug.view', 'bug.update'])->get("admin/bug/form/{$a['bug']}")->getJSON(), true)['html'];
            $this->assertStringContainsString('prova &quot;uno&quot;.txt', $dev);
            $this->assertStringContainsString('data-allegato-elimina', $dev);
            $this->assertStringContainsString('data-allegato-carica', $dev);

            $lettore = json_decode($this->come($f['utente'], ['bug.view'])->get("admin/bug/form/{$a['bug']}")->getJSON(), true)['html'];
            $this->assertStringContainsString('prova &quot;uno&quot;.txt', $lettore);
            $this->assertStringNotContainsString('data-allegato-elimina', $lettore);
            $this->assertStringNotContainsString('data-allegato-carica', $lettore);
        } finally {
            @unlink($a['file']);
            @rmdir($a['cartella']);
        }
    }

    public function testUnFileNonValidoAllaSegnalazioneBloccaTuttoSenzaCreareIlBug(): void
    {
        $f = $this->creaPianoConUnCaso();
        service('superglobals')->setFilesArray(['allegati' => [
            'name' => ['grande.png'], 'type' => ['image/png'], 'tmp_name' => [''], 'error' => [UPLOAD_ERR_INI_SIZE], 'size' => [0],
        ]]);

        try {
            $r = $this->come($f['utente'], self::TUTTI)->post('admin/esecuzioni/registra', [
                'piano_caso_id' => $f['pianoCaso'], 'passaggio_id' => $f['passaggi'][0], 'esito' => 'fallito', 'minuti_impiegati' => '1',
                'bug_titolo' => 'Con allegato', 'bug_gravita' => 'bassa',
            ] + $this->csrf());

            $this->assertSame(422, $r->response()->getStatusCode());
            $this->assertStringContainsString('dimensione massima', json_decode($r->getJSON(), true)['message']);
            $this->dontSeeInDatabase('bug', ['titolo' => 'Con allegato']);
            $this->dontSeeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $f['passaggi'][0]]);
        } finally {
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testLaSchedaDelCasoMostraDatiInCimaEIPassaggiConLeStime(): void
    {
        $f = $this->creaPianoConUnCaso();
        $casoId = (int) db_connect()->table('casi_test')->get()->getRow()->id;
        db_connect()->table('casi_test')->where('id', $casoId)->update(['precondizioni' => 'Utente loggato']);
        db_connect()->table('passaggi')->where('id', $f['passaggi'][1])->update(['risultato_atteso' => 'Coupon accettato', 'stima_minuti' => 9]);

        $r = $this->come($f['utente'], ['casi.view'])->get("admin/casi/dettaglio/{$casoId}");
        $r->assertOK();
        $html = json_decode($r->getJSON(), true)['html'];

        // Dati del test in cima...
        foreach (['Applicazione coupon', 'ShopFlow / Checkout', 'Priorità media', 'Utente loggato', '1 piano di test', '19 min'] as $atteso) {
            $this->assertStringContainsString($atteso, $html);
        }
        // ...e poi i passaggi, nell'ordine, con azione, risultato atteso e tempo.
        $this->assertLessThan(strpos($html, 'Aggiungi prodotto'), strpos($html, 'Applicazione coupon'));
        $this->assertLessThan(strpos($html, 'Inserisci coupon SCONTO10'), strpos($html, 'Aggiungi prodotto'));
        $this->assertStringContainsString('Coupon accettato', $html);
        $this->assertStringContainsString('9 min', $html);
        $this->assertStringContainsString('Totale', $html);
    }

    public function testLaSchedaDelCasoRichiedePermessoEIdEsistente(): void
    {
        $f = $this->creaPianoConUnCaso();
        $casoId = (int) db_connect()->table('casi_test')->get()->getRow()->id;

        $this->assertSame(403, $this->come($f['utente'], ['piani.view'])->get("admin/casi/dettaglio/{$casoId}")->response()->getStatusCode());
        $this->assertSame(404, $this->come($f['utente'], ['casi.view'])->get('admin/casi/dettaglio/999999')->response()->getStatusCode());
    }

    public function testOgniRigaDellElencoCasiHaIlPulsanteDiDettaglio(): void
    {
        $f = $this->creaPianoConUnCaso();
        $casoId = (int) db_connect()->table('casi_test')->get()->getRow()->id;

        $r = $this->come($f['utente'], ['casi.view'])->post('admin/casi/datatable', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => '']] + $this->csrf());

        $azioni = json_decode($r->getJSON(), true)['data'][0]['azioni'];
        $this->assertStringContainsString('data-caso-dettaglio', $azioni);
        $this->assertStringContainsString("casi&#x2F;dettaglio&#x2F;{$casoId}", $azioni, "l'indirizzo e nell'attributo data-url");
        $this->assertStringNotContainsString('data-crud-action="edit"', $azioni, 'chi puo solo consultare non vede Modifica');
    }

    public function testLaSchedaDelPianoMostraDatiInCimaEPassaggiDeiCasi(): void
    {
        $f = $this->creaPianoConUnCaso();
        db_connect()->table('passaggi')->where('id', $f['passaggi'][2])->update(['risultato_atteso' => 'Totale ridotto del 10%']);

        $r = $this->come($f['utente'], ['piani.view'])->get("admin/piani/scheda/{$f['piano']}");
        $r->assertOK();
        $html = json_decode($r->getJSON(), true)['html'];

        foreach (['Regressione 2.4', 'ShopFlow 2.4', 'Marta', '20/10/2026', '24/10/2026', '1 casi, 3 passaggi', '15 min', '17 min', '15%', 'Applicazione coupon'] as $atteso) {
            $this->assertStringContainsString($atteso, $html, $atteso);
        }
        // Dati del piano prima, passaggi dopo.
        $this->assertLessThan(strpos($html, 'Aggiungi prodotto'), strpos($html, 'Regressione 2.4'));
        $this->assertLessThan(strpos($html, 'Aggiungi prodotto'), strpos($html, 'Tester'));
        foreach (['Aggiungi prodotto', 'Inserisci coupon SCONTO10', 'Verifica totale ridotto del 10%', 'Totale ridotto del 10%', '5 min'] as $atteso) {
            $this->assertStringContainsString($atteso, $html, $atteso);
        }
        $this->assertStringNotContainsString('modificato dopo', $html, 'stime coerenti: nessun avviso');
    }

    public function testLaSchedaDelPianoSegnalaSeLaLibreriaECambiataDopo(): void
    {
        $f = $this->creaPianoConUnCaso();
        db_connect()->table('passaggi')->where('id', $f['passaggi'][0])->update(['stima_minuti' => 20]);

        $r = $this->come($f['utente'], ['piani.view'])->get("admin/piani/scheda/{$f['piano']}");

        $this->assertStringContainsString('modificato dopo', json_decode($r->getJSON(), true)['html']);
    }

    public function testLaSchedaDelPianoRichiedePermessoEIdEsistente(): void
    {
        $f = $this->creaPianoConUnCaso();

        $this->assertSame(403, $this->come($f['utente'], ['casi.view'])->get("admin/piani/scheda/{$f['piano']}")->response()->getStatusCode());
        $this->assertSame(404, $this->come($f['utente'], ['piani.view'])->get('admin/piani/scheda/999999')->response()->getStatusCode());
    }

    public function testOgniRigaDellElencoPianiHaIlPulsanteScheda(): void
    {
        $f = $this->creaPianoConUnCaso();

        $r = $this->come($f['utente'], ['piani.view'])->post('admin/piani/datatable', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => '']] + $this->csrf());

        $azioni = json_decode($r->getJSON(), true)['data'][0]['azioni'];
        $this->assertStringContainsString('data-piano-scheda', $azioni);
        $this->assertStringContainsString("piani&#x2F;scheda&#x2F;{$f['piano']}", $azioni);
        $this->assertStringContainsString("piani&#x2F;dettaglio&#x2F;{$f['piano']}", $azioni, 'il cruscotto resta disponibile');
    }

    public function testIlReportDelPianoContieneDatiRiepilogoPassaggiTentativiEBug(): void
    {
        $f = $this->creaPianoConUnCaso();
        $s = new App\Libraries\EsecuzioneService();
        $s->registra($f['pianoCaso'], $f['passaggi'][0], 'passato', 'ok al primo colpo', 3, null, $f['utente'], '2026-10-20 09:00:00');
        $s->registra($f['pianoCaso'], $f['passaggi'][2], 'fallito', 'sconto 1%', 6, ['titolo' => 'Sconto errato', 'gravita' => 'alta', 'assegnato_a' => $f['utente']], $f['utente'], '2026-10-20 09:30:00');
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;
        $s->cambiaStatoBug($bugId, 'risolto', 'formula corretta', $f['utente']);

        $r = $this->come($f['utente'], ['piani.view'])->get("admin/piani/report/{$f['piano']}");
        $r->assertOK();

        foreach ([
            'Report del piano di test', 'Regressione 2.4', 'ShopFlow 2.4', 'Marta', '20/10/2026', '24/10/2026', '15%',   // dati del piano
            'Riepilogo', '33.3%', '1/3 passaggi chiusi', 'tentativi registrati', '9 min',                           // riepilogo
            'Applicazione coupon', 'Aggiungi prodotto', 'Verifica totale ridotto del 10%', 'Da riverificare', 'Non eseguito', // passaggi
            '#1 passato', 'ok al primo colpo', '20/10/2026 09:00', '#1 fallito', 'sconto 1%',                           // tentativi
            'Bug del piano', 'Sconto errato', 'Correzione: formula corretta', 'Risolto',                                  // bug
            'window.print()',
        ] as $atteso) {
            $r->assertSee($atteso);
        }
        // Pagina autonoma: niente barra laterale dell'applicazione.
        $r->assertDontSee('app-sidebar');
    }

    public function testIlReportDiUnPianoSenzaBugDiceCheNonCeNe(): void
    {
        $f = $this->creaPianoConUnCaso();

        $this->come($f['utente'], ['piani.view'])->get("admin/piani/report/{$f['piano']}")->assertSee('Nessun bug segnalato');
    }

    public function testIlReportRichiedePermessoEPianoEsistente(): void
    {
        $f = $this->creaPianoConUnCaso();

        $this->assertSame(403, $this->come($f['utente'], ['casi.view'])->get("admin/piani/report/{$f['piano']}")->response()->getStatusCode());
        $this->come($f['utente'], ['piani.view'])->get('admin/piani/report/999999')->assertRedirectTo(site_url('admin/piani'));
    }

    public function testIlPulsanteReportCompareNellElencoENelCruscotto(): void
    {
        $f = $this->creaPianoConUnCaso();

        $elenco = $this->come($f['utente'], ['piani.view'])->post('admin/piani/datatable', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => '']] + $this->csrf());
        $this->assertStringContainsString("piani&#x2F;report&#x2F;{$f['piano']}", json_decode($elenco->getJSON(), true)['data'][0]['azioni']);

        $this->come($f['utente'], ['piani.view'])->get("admin/piani/dettaglio/{$f['piano']}")->assertSee("admin/piani/report/{$f['piano']}");
    }

    public function testLaDashboardMostraLeStatisticheSecondoIPermessi(): void
    {
        $f = $this->creaPianoConUnCaso();
        (new App\Libraries\EsecuzioneService())->registra($f['pianoCaso'], $f['passaggi'][0], 'fallito', null, 2, ['titolo' => 'Bug demo', 'gravita' => 'alta'], $f['utente']);

        $tutto = $this->come($f['utente'], self::TUTTI)->get('admin/dashboard');
        $tutto->assertOK();
        foreach (['Stato dei passaggi', 'Esecuzioni per giorno', 'Bug per stato', 'Bug da correggere per gravità', 'Tempo medio di chiusura', 'Passaggi chiusi'] as $testo) {
            $tutto->assertSee($testo);
        }

        $soloBug = $this->come($f['utente'], ['bug.view'])->get('admin/dashboard');
        $soloBug->assertOK();
        $soloBug->assertSee('Bug per stato');
        $soloBug->assertDontSee('Stato dei passaggi');

        $soloTest = $this->come($f['utente'], ['piani.view'])->get('admin/dashboard');
        $soloTest->assertOK();
        $soloTest->assertSee('Stato dei passaggi');
        $soloTest->assertDontSee('Bug per stato');

        $nulla = $this->come($f['utente'], [])->get('admin/dashboard');
        $nulla->assertOK();
        $nulla->assertDontSee('viz-root');
    }

    public function testLaGuidaSiMostraSoloConLeSezioniDelProprioRuolo(): void
    {
        $f = $this->creaPianoConUnCaso();

        $dev = $this->come($f['utente'], ['bug.view', 'bug.update'])->get('admin/guida');
        $dev->assertOK();
        $dev->assertSee('Stati di un bug');
        $dev->assertDontSee('Chiudere un piano');
        $dev->assertDontSee('Amministrazione');

        $tutto = $this->come($f['utente'], array_merge(self::TUTTI, ['utenti.view', 'log.view']))->get('admin/guida');
        $tutto->assertOK();
        $tutto->assertSee('Chiudere un piano');
        $tutto->assertSee('Amministrazione');
        $tutto->assertSee('data-diagramma');
    }

    public function testLaGuidaRichiedeSoloLAutenticazione(): void
    {
        $this->withSession([])->get('admin/guida')->assertRedirectTo(site_url('login'));

        $this->withSession(['logged_in' => true, 'user_id' => 1, 'nome' => 'X', 'permessi' => []])->get('admin/guida')->assertOK();
    }

    public function testRegistraConDataEOraSceltiViaHttp(): void
    {
        $f = $this->creaPianoConUnCaso();

        $this->come($f['utente'], self::TUTTI)->post('admin/esecuzioni/registra', [
            'piano_caso_id' => $f['pianoCaso'], 'passaggio_id' => $f['passaggi'][0], 'esito' => 'passato',
            'minuti_impiegati' => '3', 'eseguito_il' => '2026-10-09T16:40',
        ] + $this->csrf())->assertJSONFragment(['success' => true]);

        $this->seeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $f['passaggi'][0], 'eseguito_il' => '2026-10-09 16:40:00']);
    }

    public function testRegistraSenzaTempoViene422SulCampoMinuti(): void
    {
        $f = $this->creaPianoConUnCaso();

        $result = $this->come($f['utente'], self::TUTTI)->post('admin/esecuzioni/registra', [
            'piano_caso_id' => $f['pianoCaso'], 'passaggio_id' => $f['passaggi'][0], 'esito' => 'passato',
        ] + $this->csrf());

        $this->assertSame(422, $result->response()->getStatusCode());
        $this->assertArrayHasKey('minuti_impiegati', json_decode($result->getJSON(), true)['errors']);
        $this->dontSeeInDatabase('esecuzioni_passaggio', ['passaggio_id' => $f['passaggi'][0]]);
    }

    public function testTransizioneDiStatoNonValidaViene422SulCampoStato(): void
    {
        $f = $this->creaPianoConUnCaso();
        (new App\Libraries\EsecuzioneService())->registra($f['pianoCaso'], $f['passaggi'][0], 'fallito', null, 1, ['titolo' => 'Bug', 'gravita' => 'bassa'], $f['utente']);
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;

        $result = $this->come($f['utente'], self::TUTTI)->post("admin/bug/update/{$bugId}", ['stato' => 'chiuso'] + $this->csrf());

        $this->assertSame(422, $result->response()->getStatusCode());
        $this->assertArrayHasKey('stato', json_decode($result->getJSON(), true)['errors']);
    }

    public function testIPermessiSeparanoTesterESviluppatore(): void
    {
        $f = $this->creaPianoConUnCaso();
        $soloTester = ['piani.view', 'esecuzioni.view', 'esecuzioni.create', 'bug.view'];
        $soloDev    = ['piani.view', 'esecuzioni.view', 'bug.view', 'bug.update'];

        // Il tester non puo' cambiare lo stato di un bug...
        $this->assertSame(403, $this->come($f['utente'], $soloTester)->post('admin/bug/update/1', ['stato' => 'risolto'] + $this->csrf())->response()->getStatusCode());
        // ...e lo sviluppatore non puo' registrare esiti ne' chiudere piani.
        $this->assertSame(403, $this->come($f['utente'], $soloDev)->post('admin/esecuzioni/registra', ['esito' => 'passato'] + $this->csrf())->response()->getStatusCode());
        $this->assertSame(403, $this->come($f['utente'], $soloDev)->post("admin/piani/chiudi/{$f['piano']}", $this->csrf())->response()->getStatusCode());
    }

    public function testPagineDiDettaglioEdEsecuzioneSiCaricano(): void
    {
        $f = $this->creaPianoConUnCaso();

        $dettaglio = $this->come($f['utente'], self::TUTTI)->get("admin/piani/dettaglio/{$f['piano']}");
        $dettaglio->assertOK();
        $dettaglio->assertSee('Regressione 2.4');
        $dettaglio->assertSee('Applicazione coupon');

        $esecuzione = $this->come($f['utente'], self::TUTTI)->get("admin/esecuzioni/caso/{$f['pianoCaso']}");
        $esecuzione->assertOK();
        $esecuzione->assertSee('Verifica totale ridotto del 10%');

        $dashboard = $this->come($f['utente'], self::TUTTI)->get('admin/dashboard');
        $dashboard->assertOK();
        $dashboard->assertSee('Regressione 2.4');
    }

    public function testDatatableDeiModuliRispondeConIDati(): void
    {
        $f = $this->creaPianoConUnCaso();

        foreach (['prodotti' => 'ShopFlow', 'versioni' => '2.4', 'suite' => 'Checkout', 'casi' => 'Applicazione coupon', 'piani' => 'Regressione 2.4'] as $modulo => $atteso) {
            $post = ['draw' => 1, 'start' => 0, 'length' => 10, 'order' => [['column' => 0, 'dir' => 'desc']], 'search' => ['value' => ''], ] + $this->csrf();
            $risposta = $this->come($f['utente'], self::TUTTI)->post("admin/{$modulo}/datatable", $post);
            $risposta->assertOK();
            $risposta->assertJSONFragment(['recordsTotal' => 1]);
            $risposta->assertSee($atteso);
        }

        // La ricerca filtra davvero (colonne qualificate, nessuna ambiguita' con i join).
        $filtrata = $this->come($f['utente'], self::TUTTI)->post('admin/piani/datatable', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => 'inesistente']] + $this->csrf());
        $filtrata->assertJSONFragment(['recordsFiltered' => 0]);

        $bug = $this->come($f['utente'], self::TUTTI)->post('admin/bug/datatable', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => 'x']] + $this->csrf());
        $bug->assertOK();
    }

    public function testIndexEFormDiOgniModuloSiRenderizzano(): void
    {
        $f = $this->creaPianoConUnCaso();
        (new App\Libraries\EsecuzioneService())->registra($f['pianoCaso'], $f['passaggi'][0], 'fallito', null, 1, ['titolo' => 'Bug demo', 'gravita' => 'bassa'], $f['utente']);
        $bugId = (int) db_connect()->table('bug')->get()->getRow()->id;

        foreach (['prodotti', 'versioni', 'suite', 'casi', 'piani', 'bug'] as $modulo) {
            $index = $this->come($f['utente'], self::TUTTI)->get("admin/{$modulo}");
            $index->assertOK();
            $index->assertSee('tabella');
        }

        foreach (['prodotti', 'versioni', 'suite', 'casi', 'piani'] as $modulo) {
            $nuovo = $this->come($f['utente'], self::TUTTI)->get("admin/{$modulo}/form");
            $nuovo->assertOK();
            $nuovo->assertJSONFragment(['success' => true]);

            $modifica = $this->come($f['utente'], self::TUTTI)->get("admin/{$modulo}/form/1");
            $modifica->assertOK();
            $modifica->assertJSONFragment(['success' => true]);
        }

        $dettaglioBug = $this->come($f['utente'], self::TUTTI)->get("admin/bug/form/{$bugId}");
        $dettaglioBug->assertOK();
        $dettaglioBug->assertSee('Bug demo');
        $dettaglioBug->assertSee('Nuovo stato');
    }

    public function testLeOperazioniVengonoRegistrateNelLog(): void
    {
        $f = $this->creaPianoConUnCaso();

        $this->come($f['utente'], self::TUTTI)->post('admin/esecuzioni/registra', [
            'piano_caso_id' => $f['pianoCaso'], 'passaggio_id' => $f['passaggi'][0], 'esito' => 'passato', 'minuti_impiegati' => '3',
        ] + $this->csrf());

        $this->seeInDatabase('log_attivita', ['modulo' => 'esecuzioni', 'azione' => 'create', 'esito' => 1, 'utente_id' => $f['utente']]);
    }

    public function testIlSeederEIdempotenteEAssegnaTuttoAllAmministratore(): void
    {
        $db = db_connect();
        $db->table('ruoli')->insert(['nome' => 'Amministratore', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

        (new GestioneTestSeeder(config('Database')))->run();
        (new GestioneTestSeeder(config('Database')))->run();

        $this->assertSame(16, $db->table('permessi')->countAllResults());
        $this->assertSame(4, $db->table('ruoli')->countAllResults());

        $ruoloId = fn (string $nome) => (int) $db->table('ruoli')->where('nome', $nome)->get()->getRow()->id;
        $this->assertSame(16, $db->table('ruolo_permesso')->where('ruolo_id', $ruoloId('Amministratore'))->countAllResults());

        $permessiDi = static fn (int $id) => array_column(
            $db->table('ruolo_permesso rp')->select('p.chiave')->join('permessi p', 'p.id = rp.permesso_id')->where('rp.ruolo_id', $id)->get()->getResultArray(),
            'chiave'
        );
        $this->assertContains('bug.update', $permessiDi($ruoloId('Sviluppatore')));
        $this->assertNotContains('esecuzioni.create', $permessiDi($ruoloId('Sviluppatore')));
        $this->assertContains('esecuzioni.create', $permessiDi($ruoloId('Tester')));
        $this->assertNotContains('bug.update', $permessiDi($ruoloId('Tester')));
        $this->assertContains('piani.create', $permessiDi($ruoloId('Test manager')));
    }
}
