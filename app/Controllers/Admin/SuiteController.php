<?php

namespace App\Controllers\Admin;

use App\Models\ProdottoModel;
use App\Models\SuiteModel;

/**
 * Suite della libreria dei test. Condivide i permessi del modulo "casi".
 */
class SuiteController extends AdminCrudController
{
    protected string $vista     = 'admin/suite';
    protected string $titolo    = 'Suite';
    protected string $permesso  = 'casi';
    protected string $slug      = 'suite';
    protected string $etichetta = 'suite';
    protected string $genere    = 'a';

    protected array $colonne = [
        ['data' => 'id', 'label' => 'ID', 'ordina' => 'suite.id'],
        ['data' => 'prodotto_nome', 'label' => 'Prodotto', 'ordina' => 'prodotti.nome'],
        ['data' => 'nome', 'label' => 'Suite', 'ordina' => 'suite.nome'],
        ['data' => 'descrizione', 'label' => 'Descrizione', 'ordina' => 'suite.descrizione'],
    ];

    protected array $ricerca = ['suite.nome', 'suite.descrizione', 'prodotti.nome'];
    protected array $campi   = ['prodotto_id', 'nome', 'descrizione'];

    public function __construct()
    {
        $this->model = new SuiteModel();
    }

    protected function query()
    {
        return parent::query()
            ->select('prodotti.nome as prodotto_nome')
            ->join('prodotti', 'prodotti.id = suite.prodotto_id');
    }

    protected function datiForm(?array $record): array
    {
        return ['prodotti' => (new ProdottoModel())->orderBy('nome')->findAll()];
    }

    protected function bloccoEliminazione(int $id): ?string
    {
        return $this->model->casiCollegati($id) > 0
            ? 'Impossibile eliminare: la suite contiene casi di test.'
            : null;
    }
}
