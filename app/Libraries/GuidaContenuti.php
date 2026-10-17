<?php

namespace App\Libraries;

/**
 * Contenuti della pagina Guida: ogni sezione descrive un'area dell'applicazione, le
 * operazioni possibili con il permesso che le abilita e i diagrammi di flusso (sintassi
 * Mermaid, disegnati nel browser).
 *
 * Una sezione compare solo se l'utente ha almeno uno dei permessi delle sue operazioni
 * (la panoramica, senza operazioni, compare sempre). All'interno della sezione le
 * operazioni non disponibili restano elencate, segnate come tali, cosi' si capisce
 * quale permesso manca.
 */
class GuidaContenuti
{
    /**
     * @param string[] $permessiUtente
     *
     * @return list<array{
     *     id: string, titolo: string, icona: string, intro: string,
     *     operazioni: list<array{permesso: string, etichetta: string, descrizione: string, disponibile: bool}>,
     *     passi: list<string>, diagrammi: list<array{titolo: string, codice: string}>, note: list<string>
     * }>
     */
    public static function sezioniPer(array $permessiUtente): array
    {
        $visibili = [];

        foreach (self::tutte() as $sezione) {
            $operazioni = array_map(
                static fn (array $operazione) => $operazione + ['disponibile' => in_array($operazione['permesso'], $permessiUtente, true)],
                $sezione['operazioni']
            );

            $haAccesso = $operazioni === [] || array_filter($operazioni, static fn ($o) => $o['disponibile']) !== [];
            if ($haAccesso) {
                $visibili[] = ['operazioni' => $operazioni] + $sezione;
            }
        }

        return $visibili;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function tutte(): array
    {
        return [
            [
                'id'         => 'panoramica',
                'titolo'     => 'Come funziona',
                'icona'      => 'bi bi-diagram-3',
                'intro'      => 'L\'applicazione gestisce i piani di test di un prodotto software. Si scrive una volta la libreria dei test, si crea un piano per una versione, si eseguono i passaggi uno per uno e i difetti trovati seguono un ciclo fino alla correzione verificata.',
                'operazioni' => [],
                'passi'      => [
                    'Il test manager prepara prodotti, versioni, suite, casi e passaggi con i tempi stimati, poi compone un piano con date di inizio e fine.',
                    'Il tester esegue i passaggi e registra per ciascuno esito, tempo impiegato e data/ora. Se un passaggio fallisce apre un bug.',
                    'Lo sviluppatore prende in carico il bug e lo segna come risolto.',
                    'Il tester riesegue solo quel passaggio: se passa, il bug e il passaggio sono chiusi; se fallisce, il bug viene riaperto.',
                    'Quando tutti i passaggi sono chiusi il tester chiude il piano.',
                ],
                'diagrammi'  => [[
                    'titolo' => 'Il ciclo completo, per ruolo',
                    'codice' => <<<'MMD'
flowchart LR
  subgraph TM["Test manager"]
    A["Crea prodotto e versione"] --> B["Scrive suite, casi e passaggi con le stime"]
    B --> C["Crea il piano e sceglie i casi"]
  end
  subgraph T["Tester"]
    D["Esegue i passaggi e registra esito, tempo e data"]
    G["Riesegue il passaggio corretto"]
    H["Chiude il piano"]
  end
  subgraph S["Sviluppatore"]
    E["Prende in carico il bug"] --> F["Segna il bug come risolto"]
  end
  C --> D
  D -->|"passaggio fallito: si apre un bug"| E
  F --> G
  G -->|"riverifica superata"| H
  G -->|"riverifica fallita: bug riaperto"| E
MMD,
                ]],
                'note'       => ['I ruoli sono indicativi: cio che puoi fare dipende solo dai permessi del tuo ruolo, elencati nelle sezioni seguenti.'],
            ],
            [
                'id'         => 'prodotti',
                'titolo'     => 'Prodotti e versioni',
                'icona'      => 'bi bi-box-seam',
                'intro'      => 'Un prodotto e il software sotto test; le sue versioni (es. 2.4) sono i rilasci per cui si creano i piani. Si gestiscono da Libreria → Prodotti e Libreria → Versioni.',
                'operazioni' => [
                    ['permesso' => 'prodotti.view', 'etichetta' => 'Consultare', 'descrizione' => 'Vedere l\'elenco di prodotti e versioni, con ricerca e ordinamento.'],
                    ['permesso' => 'prodotti.create', 'etichetta' => 'Creare', 'descrizione' => 'Aggiungere un prodotto o una versione con il pulsante Nuovo.'],
                    ['permesso' => 'prodotti.edit', 'etichetta' => 'Modificare', 'descrizione' => 'Cambiare nome, descrizione, stato attivo o data di rilascio prevista.'],
                    ['permesso' => 'prodotti.delete', 'etichetta' => 'Eliminare', 'descrizione' => 'Rimuovere un prodotto o una versione, se non sono in uso.'],
                ],
                'passi'      => [
                    'Crea il prodotto (nome univoco).',
                    'Crea almeno una versione del prodotto: serve per creare un piano.',
                ],
                'diagrammi'  => [[
                    'titolo' => 'Creazione ed eliminazione',
                    'codice' => <<<'MMD'
flowchart LR
  A["Nuovo prodotto"] --> B["Nuova versione del prodotto"]
  B --> C["Disponibile per i piani di test"]
  D["Elimina prodotto"] --> E{"Ha versioni o suite?"}
  E -->|"si"| F["Eliminazione bloccata"]
  E -->|"no"| G["Prodotto eliminato"]
  H["Elimina versione"] --> I{"Ha piani di test?"}
  I -->|"si"| F
  I -->|"no"| J["Versione eliminata"]
MMD,
                ]],
                'note'       => ['Versioni e prodotti condividono gli stessi permessi.'],
            ],
            [
                'id'         => 'libreria',
                'titolo'     => 'Suite, casi di test e passaggi',
                'icona'      => 'bi bi-list-check',
                'intro'      => 'La libreria contiene i test riusabili. Una suite raggruppa i casi di un prodotto per area (es. Checkout); un caso e una sequenza di passaggi. Si gestiscono da Libreria → Suite e Libreria → Casi di test.',
                'operazioni' => [
                    ['permesso' => 'casi.view', 'etichetta' => 'Consultare', 'descrizione' => 'Vedere suite, casi e i loro passaggi.'],
                    ['permesso' => 'casi.create', 'etichetta' => 'Creare', 'descrizione' => 'Creare suite e casi di test, con almeno un passaggio.'],
                    ['permesso' => 'casi.edit', 'etichetta' => 'Modificare', 'descrizione' => 'Cambiare titolo, priorita e passaggi; aggiungere, riordinare o togliere passaggi.'],
                    ['permesso' => 'casi.delete', 'etichetta' => 'Eliminare', 'descrizione' => 'Rimuovere una suite vuota o un caso non incluso in nessun piano.'],
                ],
                'passi'      => [
                    'Crea la suite scegliendo il prodotto.',
                    'Crea il caso: titolo, suite, priorita e precondizioni.',
                    'Aggiungi i passaggi. Per ciascuno indica l\'azione, il risultato atteso e il tempo stimato in minuti (obbligatorio, almeno 1).',
                    'La stima del caso si calcola da sola come somma dei passaggi.',
                ],
                'diagrammi'  => [
                    [
                        'titolo' => 'Struttura della libreria',
                        'codice' => <<<'MMD'
flowchart TD
  P["Prodotto"] --> S["Suite"]
  S --> C["Caso di test"]
  C --> PA["Passaggio: azione, risultato atteso, minuti stimati"]
  PA --> T["Stima del caso = somma dei minuti dei passaggi"]
MMD,
                    ],
                    [
                        'titolo' => 'Cosa si puo togliere',
                        'codice' => <<<'MMD'
flowchart TD
  A["Togli un passaggio da un caso"] --> B{"Il passaggio ha esecuzioni registrate?"}
  B -->|"si"| C["Bloccato: lo storico si conserva"]
  B -->|"no"| D["Passaggio eliminato"]
  E["Elimina un caso"] --> F{"E incluso in un piano?"}
  F -->|"si"| G["Bloccato"]
  F -->|"no"| H["Caso eliminato"]
  I["Elimina una suite"] --> J{"Contiene casi?"}
  J -->|"si"| G
  J -->|"no"| K["Suite eliminata"]
MMD,
                    ],
                ],
                'note'       => ['Modificare i tempi di un caso non cambia le stime dei piani gia creati: ogni piano conserva la stima che il caso aveva quando e stato inserito.'],
            ],
            [
                'id'         => 'piani',
                'titolo'     => 'Piani di test',
                'icona'      => 'bi bi-calendar-check',
                'intro'      => 'Un piano collega una versione, un tester, un periodo e un insieme di casi della libreria. Si gestisce da Test → Piani di test.',
                'operazioni' => [
                    ['permesso' => 'piani.view', 'etichetta' => 'Consultare', 'descrizione' => 'Vedere l\'elenco dei piani con la barra di avanzamento e aprire il cruscotto di ciascuno.'],
                    ['permesso' => 'piani.create', 'etichetta' => 'Creare', 'descrizione' => 'Creare un piano scegliendo versione, tester, date, extra per le riverifiche e casi.'],
                    ['permesso' => 'piani.edit', 'etichetta' => 'Modificare', 'descrizione' => 'Cambiare dati e casi del piano finche non e chiuso.'],
                    ['permesso' => 'piani.delete', 'etichetta' => 'Eliminare', 'descrizione' => 'Eliminare un piano che non ha ancora esecuzioni.'],
                ],
                'passi'      => [
                    'Scegli la versione e dai un nome al piano.',
                    'Assegna il tester e indica data di inizio e fine prevista.',
                    'Imposta la percentuale di tempo extra per le riverifiche (predefinita 15%): si somma alla stima dei casi.',
                    'Seleziona i casi da includere. Ognuno porta con se la stima in minuti.',
                ],
                'diagrammi'  => [[
                    'titolo' => 'Stati di un piano',
                    'codice' => <<<'MMD'
stateDiagram-v2
  [*] --> Bozza
  Bozza --> InCorso: prima esecuzione registrata
  InCorso --> Chiuso: tutti i passaggi chiusi e conferma del tester
  Chiuso --> [*]
  state "In corso" as InCorso
MMD,
                ]],
                'note'       => [
                    'Un caso gia eseguito non si puo togliere dal piano.',
                    'Un piano chiuso non si modifica.',
                ],
            ],
            [
                'id'         => 'cruscotto',
                'titolo'     => 'Cruscotto: avanzamento, ritardo e stime',
                'icona'      => 'bi bi-bar-chart-line',
                'intro'      => 'Aprendo un piano si vede a che punto e. Nulla e inserito a mano: tutto e calcolato dalle esecuzioni registrate.',
                'operazioni' => [
                    ['permesso' => 'piani.view', 'etichetta' => 'Aprire il cruscotto', 'descrizione' => 'Dal pulsante con il grafico nell\'elenco piani, o dal nome del piano; un riepilogo dei piani aperti e anche nella Dashboard.'],
                ],
                'passi'      => [
                    'Avanzamento reale: passaggi chiusi diviso passaggi totali.',
                    'Avanzamento atteso: giorni trascorsi diviso giorni del piano (la tacca nera sulla barra).',
                    'Ritardo: se il reale e sotto l\'atteso, la differenza espressa in giorni.',
                    'Fine proiettata: al ritmo di passaggi chiusi per giorno, quando finirebbe il piano. Se supera la fine prevista il piano e "a rischio".',
                    'Tempo: stima totale (con l\'extra per le riverifiche) contro la somma dei minuti realmente impiegati, riverifiche comprese.',
                    '"Fermi" sono i passaggi bloccati o falliti con bug ancora aperto.',
                ],
                'diagrammi'  => [[
                    'titolo' => 'Come si arriva al ritardo e al rischio',
                    'codice' => <<<'MMD'
flowchart TD
  A["Passaggi chiusi / passaggi totali = avanzamento reale"] --> C
  B["Giorni trascorsi / giorni del piano = avanzamento atteso"] --> C
  C{"Il reale e sotto l'atteso?"}
  C -->|"si"| D["In ritardo: la differenza vale i giorni di ritardo"]
  C -->|"no"| E["Nei tempi"]
  D --> F["Fine proiettata = oggi + passaggi mancanti / ritmo giornaliero"]
  E --> F
  F --> G{"La fine proiettata supera la fine prevista?"}
  G -->|"si"| H["Piano a rischio"]
  G -->|"no"| I["Piano in linea"]
MMD,
                ]],
                'note'       => ['I giorni sono di calendario, estremi inclusi. Senza passaggi chiusi la fine proiettata non e ancora calcolabile.'],
            ],
            [
                'id'         => 'esecuzione',
                'titolo'     => 'Eseguire i test',
                'icona'      => 'bi bi-play-circle',
                'intro'      => 'Si esegue un caso alla volta, passaggio per passaggio, dalla pagina del caso (pulsante Esegui nel cruscotto del piano).',
                'operazioni' => [
                    ['permesso' => 'esecuzioni.view', 'etichetta' => 'Consultare', 'descrizione' => 'Aprire i casi di un piano e vedere lo storico dei tentativi di ogni passaggio.'],
                    ['permesso' => 'esecuzioni.create', 'etichetta' => 'Registrare e correggere', 'descrizione' => 'Registrare l\'esito di un passaggio, correggere in seguito tempo, note e data/ora di un tentativo, chiudere il piano.'],
                ],
                'passi'      => [
                    'Premi Passato, Fallito, Bloccato o Salta sul passaggio (quello evidenziato e il prossimo da fare).',
                    'Nella finestra indica data e ora (di default adesso, mai nel futuro) e il tempo impiegato in minuti, anche 0. Per "Salta" il tempo non serve.',
                    'Se il passaggio fallisce compila il bug: titolo, descrizione, gravita e a chi assegnarlo.',
                    'Ogni tentativo resta nello storico. Con il link "modifica" puoi correggere tempo, note e data/ora; l\'esito invece non si cambia.',
                ],
                'diagrammi'  => [
                    [
                        'titolo' => 'Registrare un passaggio',
                        'codice' => <<<'MMD'
flowchart TD
  A["Apri il caso dal piano"] --> B["Scegli il passaggio evidenziato"]
  B --> C["Premi Passato, Fallito, Bloccato o Salta"]
  C --> D["Imposta data e ora e il tempo impiegato"]
  D --> E{"Esito"}
  E -->|"Passato"| F["Passaggio chiuso"]
  E -->|"Fallito"| G["Compila il bug: titolo, gravita, assegnatario"]
  G --> H["Passaggio fermo fino alla correzione"]
  E -->|"Bloccato o Salta"| I["Resta eseguibile in un secondo momento"]
MMD,
                    ],
                    [
                        'titolo' => 'Stati di un passaggio',
                        'codice' => <<<'MMD'
stateDiagram-v2
  [*] --> DaEseguire
  DaEseguire --> Chiuso: esito passato
  DaEseguire --> Fallito: esito fallito con apertura del bug
  DaEseguire --> Bloccato: esito bloccato
  DaEseguire --> Saltato: esito saltato
  Bloccato --> DaEseguire: nuovo tentativo
  Saltato --> DaEseguire: nuovo tentativo
  Fallito --> DaRiverificare: bug risolto dallo sviluppatore
  DaRiverificare --> Chiuso: riverifica superata
  DaRiverificare --> Fallito: riverifica fallita e bug riaperto
  Chiuso --> [*]
  state "Da eseguire" as DaEseguire
  state "Da riverificare" as DaRiverificare
MMD,
                    ],
                    [
                        'titolo' => 'Correggere un tentativo gia registrato',
                        'codice' => <<<'MMD'
flowchart TD
  A["Link modifica accanto al tentativo"] --> B{"Il piano e chiuso?"}
  B -->|"si"| C["Non modificabile"]
  B -->|"no"| D["Cambia tempo, note, data e ora"]
  D --> E{"La data sta tra il tentativo precedente e il successivo?"}
  E -->|"no"| F["Errore: correggi la data"]
  E -->|"si"| G["Salvato. L'esito resta invariato"]
MMD,
                    ],
                ],
                'note'       => ['Un passaggio fallito non si puo rieseguire finche lo sviluppatore non ha risolto il bug.'],
            ],
            [
                'id'         => 'riverifiche',
                'titolo'     => 'Riverifiche',
                'icona'      => 'bi bi-arrow-repeat',
                'intro'      => 'Quando lo sviluppatore risolve un bug, solo il passaggio che lo aveva scoperto va rieseguito. Li trovi in Test → Riverifiche (di default i tuoi piani, con un link per vederli tutti).',
                'operazioni' => [
                    ['permesso' => 'esecuzioni.view', 'etichetta' => 'Vedere la coda', 'descrizione' => 'Elenco dei passaggi con bug risolto in attesa di riverifica, con la nota di correzione.'],
                    ['permesso' => 'esecuzioni.create', 'etichetta' => 'Riverificare', 'descrizione' => 'Rieseguire il passaggio: nuovo tentativo con il suo tempo e la sua data.'],
                ],
                'passi'      => [
                    'Apri Riverifiche e scegli il passaggio.',
                    'Esegui di nuovo il passaggio e registra l\'esito.',
                    'Se passa il bug si chiude da solo; se fallisce lo stesso bug viene riaperto, senza crearne uno nuovo.',
                ],
                'diagrammi'  => [[
                    'titolo' => 'Il ciclo di riverifica',
                    'codice' => <<<'MMD'
flowchart TD
  A["Riverifiche: passaggi con bug risolto"] --> B["Apri il caso e riesegui il passaggio"]
  B --> C{"Esito"}
  C -->|"Passato"| D["Passaggio chiuso e bug chiuso"]
  C -->|"Fallito"| E["Stesso bug riaperto, storico conservato"]
  E --> F["Lo sviluppatore lo risolve di nuovo"]
  F --> A
MMD,
                ]],
                'note'       => [],
            ],
            [
                'id'         => 'bug',
                'titolo'     => 'Bug',
                'icona'      => 'bi bi-bug',
                'intro'      => 'Un bug nasce sempre da un passaggio fallito e resta legato a quel passaggio. Si consulta da Test → Bug; il pulsante Dettaglio mostra il contesto completo (prodotto, piano, caso, passaggio, note del tester).',
                'operazioni' => [
                    ['permesso' => 'bug.view', 'etichetta' => 'Consultare', 'descrizione' => 'Vedere l\'elenco dei bug e il dettaglio di ciascuno.'],
                    ['permesso' => 'bug.update', 'etichetta' => 'Lavorare il bug', 'descrizione' => 'Prenderlo in carico e segnarlo come risolto, con una nota su cosa e stato corretto.'],
                ],
                'passi'      => [
                    'Apri il dettaglio del bug e leggi passaggio, risultato atteso e note del tester.',
                    'Scegli "In lavorazione" quando inizi: se il bug non era assegnato, lo diventi tu.',
                    'Quando hai corretto, scegli "Risolto" e descrivi la correzione: il tester la vedra nella coda delle riverifiche.',
                    '"Chiuso" e "Riaperto" non si impostano a mano: li decide l\'esito della riverifica.',
                ],
                'diagrammi'  => [[
                    'titolo' => 'Stati di un bug',
                    'codice' => <<<'MMD'
stateDiagram-v2
  [*] --> Aperto
  Aperto --> InLavorazione: lo sviluppatore lo prende in carico
  Aperto --> Risolto: correzione immediata
  InLavorazione --> Risolto: correzione completata
  Risolto --> Chiuso: riverifica superata dal tester
  Risolto --> Riaperto: riverifica fallita dal tester
  Riaperto --> InLavorazione
  Riaperto --> Risolto
  Chiuso --> [*]
  state "In lavorazione" as InLavorazione
MMD,
                ]],
                'note'       => ['I bug non si creano ne si eliminano dall\'elenco: li apre il tester dal passaggio fallito.'],
            ],
            [
                'id'         => 'chiusura',
                'titolo'     => 'Chiudere un piano',
                'icona'      => 'bi bi-check2-circle',
                'intro'      => 'Quando tutti i passaggi sono chiusi il cruscotto lo segnala e compare il pulsante "Chiudi il piano".',
                'operazioni' => [
                    ['permesso' => 'esecuzioni.create', 'etichetta' => 'Chiudere il piano', 'descrizione' => 'Confermare la chiusura: viene registrata la data di fine effettiva.'],
                ],
                'passi'      => [
                    'Verifica sul cruscotto che non restino passaggi aperti, bloccati o da riverificare.',
                    'Premi "Chiudi il piano" e conferma.',
                ],
                'diagrammi'  => [[
                    'titolo' => 'Condizioni per la chiusura',
                    'codice' => <<<'MMD'
flowchart TD
  A{"Tutti i passaggi sono chiusi?"}
  A -->|"no"| B["Chiusura non disponibile: restano passaggi da fare, bloccati o da riverificare"]
  A -->|"si"| C["Compare Chiudi il piano"]
  C --> D["Il tester conferma"]
  D --> E["Piano chiuso con la data di fine effettiva"]
  E --> F["Piano e esecuzioni non piu modificabili"]
MMD,
                ]],
                'note'       => [],
            ],
            [
                'id'         => 'amministrazione',
                'titolo'     => 'Amministrazione',
                'icona'      => 'bi bi-gear',
                'intro'      => 'Nel gruppo Admin si gestiscono chi puo accedere e cosa puo fare, e si controlla lo storico delle operazioni.',
                'operazioni' => [
                    ['permesso' => 'utenti.view', 'etichetta' => 'Utenti: consultare', 'descrizione' => 'Vedere l\'elenco degli utenti.'],
                    ['permesso' => 'utenti.create', 'etichetta' => 'Utenti: creare', 'descrizione' => 'Creare un utente con ruolo e password.'],
                    ['permesso' => 'utenti.edit', 'etichetta' => 'Utenti: modificare', 'descrizione' => 'Cambiare dati, ruolo, stato attivo e password.'],
                    ['permesso' => 'utenti.delete', 'etichetta' => 'Utenti: eliminare', 'descrizione' => 'Disattivare un utente (cancellazione logica).'],
                    ['permesso' => 'ruoli.view', 'etichetta' => 'Ruoli: consultare', 'descrizione' => 'Vedere i ruoli.'],
                    ['permesso' => 'ruoli.create', 'etichetta' => 'Ruoli: creare', 'descrizione' => 'Creare un ruolo scegliendo i suoi permessi.'],
                    ['permesso' => 'ruoli.edit', 'etichetta' => 'Ruoli: modificare', 'descrizione' => 'Cambiare nome e permessi di un ruolo.'],
                    ['permesso' => 'ruoli.delete', 'etichetta' => 'Ruoli: eliminare', 'descrizione' => 'Eliminare un ruolo non assegnato a nessun utente.'],
                    ['permesso' => 'permessi.view', 'etichetta' => 'Permessi: consultare', 'descrizione' => 'Vedere l\'elenco dei permessi.'],
                    ['permesso' => 'permessi.create', 'etichetta' => 'Permessi: creare', 'descrizione' => 'Aggiungere un permesso.'],
                    ['permesso' => 'permessi.edit', 'etichetta' => 'Permessi: modificare', 'descrizione' => 'Modificare un permesso esistente.'],
                    ['permesso' => 'permessi.delete', 'etichetta' => 'Permessi: eliminare', 'descrizione' => 'Eliminare un permesso non in uso.'],
                    ['permesso' => 'log.view', 'etichetta' => 'Registro attivita', 'descrizione' => 'Consultare chi ha fatto cosa e quando.'],
                ],
                'passi'      => [
                    'I permessi sono le singole autorizzazioni (es. bug.update).',
                    'Un ruolo raccoglie un insieme di permessi (es. Tester).',
                    'Ogni utente ha un ruolo; le modifiche ai permessi di un ruolo valgono dal login successivo (per il proprio ruolo, subito).',
                ],
                'diagrammi'  => [[
                    'titolo' => 'Permessi, ruoli e utenti',
                    'codice' => <<<'MMD'
flowchart LR
  P["Permessi"] -->|"raccolti in"| R["Ruolo"]
  R -->|"assegnato a"| U["Utente"]
  U --> O["Puo compiere solo le operazioni dei suoi permessi"]
  O --> L["Ogni modifica e registrata nel Registro attivita"]
MMD,
                ]],
                'note'       => [],
            ],
        ];
    }
}
