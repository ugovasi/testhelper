<?php

namespace Tests\Support\Libraries;

/**
 * Fixture condivisa dai test della gestione piani di test: replica il caso CT-02
 * ("Applicazione coupon", 3 passaggi) dell'esempio d'uso, dentro un piano di 5 giorni.
 */
trait CreaFixture
{
    /**
     * @return array{piano: int, pianoCaso: int, passaggi: list<int>, utente: int}
     */
    protected function creaPianoConUnCaso(string $inizio = '2026-10-20', string $fine = '2026-10-24'): array
    {
        $db  = db_connect();
        $ora = date('Y-m-d H:i:s');

        $db->table('utenti')->insert(['nome' => 'Marta', 'email' => 'marta@example.com', 'password_hash' => 'x', 'attivo' => 1, 'created_at' => $ora, 'updated_at' => $ora]);
        $utente = (int) $db->insertID();

        $db->table('prodotti')->insert(['nome' => 'ShopFlow', 'attivo' => 1, 'created_at' => $ora, 'updated_at' => $ora]);
        $prodotto = (int) $db->insertID();

        $db->table('versioni')->insert(['prodotto_id' => $prodotto, 'nome' => '2.4', 'created_at' => $ora, 'updated_at' => $ora]);
        $versione = (int) $db->insertID();

        $db->table('suite')->insert(['prodotto_id' => $prodotto, 'nome' => 'Checkout', 'created_at' => $ora, 'updated_at' => $ora]);
        $suite = (int) $db->insertID();

        $db->table('casi_test')->insert(['suite_id' => $suite, 'titolo' => 'Applicazione coupon', 'priorita' => 'media', 'stima_minuti' => 15, 'created_at' => $ora, 'updated_at' => $ora]);
        $caso = (int) $db->insertID();

        $passaggi = [];
        foreach (['Aggiungi prodotto', 'Inserisci coupon SCONTO10', 'Verifica totale ridotto del 10%'] as $indice => $azione) {
            $db->table('passaggi')->insert(['caso_id' => $caso, 'ordine' => $indice + 1, 'azione' => $azione, 'stima_minuti' => 5, 'created_at' => $ora, 'updated_at' => $ora]);
            $passaggi[] = (int) $db->insertID();
        }

        $db->table('piani_test')->insert([
            'versione_id' => $versione, 'nome' => 'Regressione 2.4', 'assegnatario_id' => $utente,
            'data_inizio' => $inizio, 'data_fine_prevista' => $fine, 'quota_riverifica' => 15,
            'stato' => 'bozza', 'created_at' => $ora, 'updated_at' => $ora,
        ]);
        $piano = (int) $db->insertID();

        $db->table('piano_casi')->insert(['piano_id' => $piano, 'caso_id' => $caso, 'ordine' => 1, 'stima_minuti' => 15, 'created_at' => $ora, 'updated_at' => $ora]);

        return ['piano' => $piano, 'pianoCaso' => (int) $db->insertID(), 'passaggi' => $passaggi, 'utente' => $utente];
    }
}
