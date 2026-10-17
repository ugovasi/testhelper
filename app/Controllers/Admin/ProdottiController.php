<?php

namespace App\Controllers\Admin;

use App\Models\ProdottoModel;

class ProdottiController extends AdminCrudController
{
    protected string $vista     = 'admin/prodotti';
    protected string $titolo    = 'Prodotti';
    protected string $permesso  = 'prodotti';
    protected string $slug      = 'prodotti';
    protected string $etichetta = 'prodotto';

    protected array $colonne = [
        ['data' => 'id', 'label' => 'ID', 'ordina' => 'prodotti.id'],
        ['data' => 'nome', 'label' => 'Nome', 'ordina' => 'prodotti.nome'],
        ['data' => 'descrizione', 'label' => 'Descrizione', 'ordina' => 'prodotti.descrizione'],
        ['data' => 'attivo_html', 'label' => 'Stato', 'ordina' => 'prodotti.attivo', 'html' => true],
    ];

    protected array $ricerca = ['prodotti.nome', 'prodotti.descrizione'];
    protected array $campi   = ['nome', 'descrizione'];

    public function __construct()
    {
        $this->model = new ProdottoModel();
    }

    protected function dati(): array
    {
        return parent::dati() + ['attivo' => $this->request->getPost('attivo') ? 1 : 0];
    }

    protected function preparaRiga(array $riga): array
    {
        $riga['attivo_html'] = $riga['attivo']
            ? '<span class="badge text-bg-success">Attivo</span>'
            : '<span class="badge text-bg-secondary">Non attivo</span>';

        return $riga;
    }

    protected function bloccoEliminazione(int $id): ?string
    {
        return $this->model->elementiCollegati($id) > 0
            ? 'Impossibile eliminare: il prodotto ha versioni o suite collegate.'
            : null;
    }
}
