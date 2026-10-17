<?php

namespace App\Controllers\Admin;

use App\Models\ProdottoModel;
use App\Models\VersioneModel;

/**
 * Versioni dei prodotti. Condivide i permessi del modulo prodotti: sono parte della
 * stessa anagrafica e non hanno senso assegnate separatamente.
 */
class VersioniController extends AdminCrudController
{
    protected string $vista     = 'admin/versioni';
    protected string $titolo    = 'Versioni';
    protected string $permesso  = 'prodotti';
    protected string $slug      = 'versioni';
    protected string $etichetta = 'versione';
    protected string $genere    = 'a';

    protected array $colonne = [
        ['data' => 'id', 'label' => 'ID', 'ordina' => 'versioni.id'],
        ['data' => 'prodotto_nome', 'label' => 'Prodotto', 'ordina' => 'prodotti.nome'],
        ['data' => 'nome', 'label' => 'Versione', 'ordina' => 'versioni.nome'],
        ['data' => 'data_rilascio_prevista', 'label' => 'Rilascio previsto', 'ordina' => 'versioni.data_rilascio_prevista'],
    ];

    protected array $ricerca = ['versioni.nome', 'prodotti.nome'];
    protected array $campi   = ['prodotto_id', 'nome', 'data_rilascio_prevista'];

    public function __construct()
    {
        $this->model = new VersioneModel();
    }

    protected function query()
    {
        return parent::query()
            ->select('prodotti.nome as prodotto_nome')
            ->join('prodotti', 'prodotti.id = versioni.prodotto_id');
    }

    protected function datiForm(?array $record): array
    {
        return ['prodotti' => (new ProdottoModel())->orderBy('nome')->findAll()];
    }

    protected function bloccoEliminazione(int $id): ?string
    {
        return $this->model->pianiCollegati($id) > 0
            ? 'Impossibile eliminare: la versione ha piani di test collegati.'
            : null;
    }
}
