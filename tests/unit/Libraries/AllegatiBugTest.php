<?php

use App\Libraries\AllegatiBug;
use App\Libraries\EsecuzioneService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Libraries\CreaFixture;

/**
 * @internal
 */
final class AllegatiBugTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CreaFixture;

    protected $refresh   = true;
    protected $namespace = 'App';

    private string $cartella;

    /** @var list<string> */
    private array $daPulire = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartella = sys_get_temp_dir() . '/allegati-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach ($this->daPulire as $file) {
            @unlink($file);
        }
        foreach (glob($this->cartella . '/*') ?: [] as $sotto) {
            array_map('unlink', glob($sotto . '/*') ?: []);
            @rmdir($sotto);
        }
        @rmdir($this->cartella);
        parent::tearDown();
    }

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * File "caricato" finto: nei test non esiste un vero upload HTTP, quindi isValid() e
     * move() (che si appoggiano a is_uploaded_file/move_uploaded_file) sono sostituiti da
     * una copia. Tutta la logica dell'applicazione sopra di essi resta quella vera.
     */
    private function file(string $nomeClient, string $contenuto, ?int $dimensione = null, int $errore = UPLOAD_ERR_OK): UploadedFile
    {
        $percorso = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($percorso, $contenuto);
        $this->daPulire[] = $percorso;

        return new class($percorso, $nomeClient, null, $dimensione ?? strlen($contenuto), $errore) extends UploadedFile {
            public function move(string $targetPath, ?string $name = null, bool $overwrite = false)
            {
                copy($this->path, rtrim($targetPath, '/') . '/' . $name);
                $this->hasMoved = true;

                return true;
            }
        };
    }

    private function png(string $nome = 'schermata.png'): UploadedFile
    {
        return $this->file($nome, base64_decode(self::PNG));
    }

    private function creaBug(): int
    {
        $f = $this->creaPianoConUnCaso();
        (new EsecuzioneService())->registra($f['pianoCaso'], $f['passaggi'][0], 'fallito', null, 1, ['titolo' => 'Bug', 'gravita' => 'bassa'], $f['utente']);

        return (int) db_connect()->table('bug')->get()->getRow()->id;
    }

    public function testUnaImmagineValidaSiSalvaConNomeCasualeFuoriDallaCartellaDellUtente(): void
    {
        $bugId    = $this->creaBug();
        $archivio = new AllegatiBug($this->cartella);
        $file     = $this->png('../../etc/passwd.png');

        $this->assertNull($archivio->valida([$file]));
        $this->assertSame(1, $archivio->salva($bugId, [$file], 7));

        $riga = db_connect()->table('bug_allegati')->get()->getRowArray();
        $this->assertSame('passwd.png', $riga['nome_originale'], 'il percorso fornito dal client viene scartato');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.png$/', $riga['nome_file']);
        $this->assertSame('image/png', $riga['mime']);
        $this->assertSame(7, (int) $riga['caricato_da']);
        $this->assertFileExists($this->cartella . "/{$bugId}/" . $riga['nome_file']);

        $elenco = $archivio->elenco($bugId);
        $this->assertCount(1, $elenco);
        $this->assertTrue($elenco[0]['immagine']);
        $this->assertSame([$bugId => 1], $archivio->conteggi([$bugId]));
    }

    public function testTipiNonAmmessiVengonoRifiutati(): void
    {
        $archivio = new AllegatiBug($this->cartella);

        foreach (['virus.exe', 'pagina.html', 'script.php', 'disegno.svg', 'senza-estensione'] as $nome) {
            $errore = $archivio->valida([$this->file($nome, 'contenuto')]);

            $this->assertNotNull($errore, $nome);
            $this->assertStringContainsString('tipo di file non consentito', $errore);
        }
    }

    public function testIlContenutoDeveCorrispondereAllEstensione(): void
    {
        $archivio = new AllegatiBug($this->cartella);

        $errore = $archivio->valida([$this->file('finta.png', '<html><script>alert(1)</script></html>')]);

        $this->assertNotNull($errore);
        $this->assertStringContainsString('non corrisponde', $errore);
    }

    public function testFileTestualiEPdfSonoAmmessi(): void
    {
        $archivio = new AllegatiBug($this->cartella);

        $this->assertNull($archivio->valida([
            $this->file('errore.log', "riga 1\nriga 2\n"),
            $this->file('dati.csv', "a,b\n1,2\n"),
            $this->file('nota.txt', 'ciao'),
            $this->file('doc.pdf', "%PDF-1.4\n%%EOF\n"),
        ]));
    }

    public function testTroppiFileOTroppoGrandiVengonoRifiutati(): void
    {
        $archivio = new AllegatiBug($this->cartella);

        $sei = array_map(fn ($i) => $this->png("s{$i}.png"), range(1, AllegatiBug::MAX_FILE + 1));
        $this->assertStringContainsString('al massimo', (string) $archivio->valida($sei));

        $grande = $this->file('grande.png', base64_decode(self::PNG), AllegatiBug::limiteByte() + 1);
        $this->assertStringContainsString('dimensione massima', (string) $archivio->valida([$grande]));

        $troppoPerPhp = $this->file('php.png', '', 0, UPLOAD_ERR_INI_SIZE);
        $this->assertStringContainsString('dimensione massima', (string) $archivio->valida([$troppoPerPhp]));

        $interrotto = $this->file('x.png', '', 0, UPLOAD_ERR_PARTIAL);
        $this->assertStringContainsString('non e riuscito', (string) $archivio->valida([$interrotto]));
    }

    public function testIlLimiteNonSuperaMaiQuelloDellApplicazione(): void
    {
        $this->assertGreaterThan(0, AllegatiBug::limiteByte());
        $this->assertLessThanOrEqual(AllegatiBug::MAX_BYTE, AllegatiBug::limiteByte());
    }

    public function testSoloCaricatiScartaICampiVuoti(): void
    {
        $vuoto = $this->file('', '', 0, UPLOAD_ERR_NO_FILE);
        $vero  = $this->png();

        $this->assertSame([], AllegatiBug::soloCaricati([$vuoto]));
        $this->assertSame([$vero], AllegatiBug::soloCaricati([$vuoto, $vero]));
        $this->assertSame([], AllegatiBug::soloCaricati(null));
    }

    public function testEliminaRimuoveRigaEFile(): void
    {
        $bugId    = $this->creaBug();
        $archivio = new AllegatiBug($this->cartella);
        $archivio->salva($bugId, [$this->png()], null);

        $allegato = $archivio->elenco($bugId)[0];
        $percorso = $archivio->percorso($allegato);
        $this->assertFileExists($percorso);

        $this->assertTrue($archivio->elimina((int) $allegato['id']));

        $this->assertFileDoesNotExist($percorso);
        $this->assertSame([], $archivio->elenco($bugId));
        $this->assertFalse($archivio->elimina((int) $allegato['id']), 'gia eliminato');
    }

    public function testIlPercorsoNonPuoUscireDallaCartellaDelBug(): void
    {
        $archivio = new AllegatiBug($this->cartella);

        $percorso = $archivio->percorso(['bug_id' => 3, 'nome_file' => '../../../etc/passwd']);

        $this->assertSame($this->cartella . '/3/passwd', $percorso);
    }
}
