<?php

use App\Libraries\GuidaContenuti;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class GuidaContenutiTest extends CIUnitTestCase
{
    /** @return list<string> */
    private function idSezioni(array $permessi): array
    {
        return array_column(GuidaContenuti::sezioniPer($permessi), 'id');
    }

    public function testSenzaPermessiSiVedeSoloLaPanoramica(): void
    {
        $this->assertSame(['panoramica'], $this->idSezioni([]));
    }

    public function testUnTesterVedeEseguireRiverificareEChiudereMaNonLaLibreriaNeIBug(): void
    {
        $sezioni = $this->idSezioni(['piani.view', 'esecuzioni.view', 'esecuzioni.create', 'bug.view', 'prodotti.view', 'casi.view']);

        foreach (['panoramica', 'esecuzione', 'riverifiche', 'chiusura', 'cruscotto', 'bug', 'prodotti', 'libreria'] as $atteso) {
            $this->assertContains($atteso, $sezioni);
        }
        $this->assertNotContains('amministrazione', $sezioni);
    }

    public function testUnoSviluppatoreNonVedeLaChiusuraDelPiano(): void
    {
        $sezioni = $this->idSezioni(['piani.view', 'esecuzioni.view', 'bug.view', 'bug.update']);

        $this->assertContains('bug', $sezioni);
        $this->assertNotContains('chiusura', $sezioni, 'la chiusura richiede esecuzioni.create');
        $this->assertNotContains('prodotti', $sezioni);
    }

    public function testLeOperazioniSenzaPermessoRestanoElencateMaNonDisponibili(): void
    {
        $sezioni = GuidaContenuti::sezioniPer(['bug.view']);
        $bug = current(array_filter($sezioni, static fn ($s) => $s['id'] === 'bug'));

        $stato = array_column($bug['operazioni'], 'disponibile', 'permesso');
        $this->assertTrue($stato['bug.view']);
        $this->assertFalse($stato['bug.update']);
    }

    public function testOgniSezioneHaIdUniciIconeEDiagrammiNonVuoti(): void
    {
        $tutte = GuidaContenuti::tutte();
        $id    = array_column($tutte, 'id');

        $this->assertSame($id, array_unique($id));
        foreach ($tutte as $sezione) {
            $this->assertNotSame('', $sezione['titolo']);
            $this->assertNotSame([], $sezione['diagrammi'], "sezione {$sezione['id']} senza diagrammi");
            foreach ($sezione['diagrammi'] as $diagramma) {
                $this->assertMatchesRegularExpression('/^(flowchart|stateDiagram-v2)\b/', trim($diagramma['codice']));
            }
        }
    }

    public function testTuttiIPermessiCitatiNellaGuidaEsistonoNelSeeder(): void
    {
        $noti = ['log.view'];
        foreach (['utenti', 'ruoli', 'permessi', 'prodotti', 'casi', 'piani'] as $modulo) {
            foreach (['view', 'create', 'edit', 'delete'] as $azione) {
                $noti[] = "{$modulo}.{$azione}";
            }
        }
        array_push($noti, 'esecuzioni.view', 'esecuzioni.create', 'bug.view', 'bug.update');

        foreach (GuidaContenuti::tutte() as $sezione) {
            foreach ($sezione['operazioni'] as $operazione) {
                $this->assertContains($operazione['permesso'], $noti, "permesso sconosciuto: {$operazione['permesso']}");
            }
        }
    }
}
