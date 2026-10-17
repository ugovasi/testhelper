# TestHelper

Applicativo web PHP con autenticazione, controllo accessi basato su ruoli (RBAC) e
operazioni CRUD interamente gestite tramite form modali AJAX, costruito su
CodeIgniter 4 + AdminLTE 4 + Bootstrap 5 + DataTables.

## Funzionalità

- **Autenticazione**: login/logout con sessione, password con hash bcrypt
  (`password_hash`/`password_verify`), messaggio d'errore generico che non rivela
  se a essere errata è l'email o la password.
- **RBAC (Role-Based Access Control)**: tre entità collegate — `utenti` → `ruoli` →
  `permessi` (tabella pivot `ruolo_permesso`). Ogni rotta protetta richiede una
  permission specifica (es. `utenti.edit`); la sidebar e i pulsanti di azione si
  nascondono automaticamente per chi non ha il permesso.
- **CRUD via form modali**: tre moduli amministrativi — Utenti, Ruoli (con matrice
  di permessi a checkbox), Permessi — ciascuno con una DataTable server-side e un
  unico modal Bootstrap per creare/modificare, popolato e salvato via AJAX. Nessuna
  pagina separata di create/edit.
- **Sicurezza**:
  - CSRF in modalità *session* con token rinnovato a ogni risposta via header
    `X-CSRF-TOKEN` (necessario perché i form vivono in modali e non ricaricano la
    pagina).
  - `$allowedFields` espliciti su ogni Model (niente mass assignment).
  - Whitelisting delle colonne ordinabili/filtrabili nelle query DataTable.
  - Escaping lato server (`esc()`) e lato client (`$.fn.dataTable.render.text()`)
    per prevenire XSS nei dati mostrati in tabella.
  - Regole di business: un utente non può eliminare il proprio account; un ruolo o
    un permesso ancora assegnati non sono eliminabili.
  - Tracciamento "chi": oltre a `created_at`/`updated_at`, ogni riga di `ruoli`,
    `permessi` e `utenti` registra `created_by`/`updated_by` — l'id dell'utente
    che ha creato/modificato quella riga, valorizzato automaticamente da
    `App\Models\BaseModel` (nessun controller lo imposta esplicitamente). Nelle
    tabelle delle tre sezioni, al posto della colonna "Creato il" c'è
    un'icona informativa che apre un popover con creato il/da e modificato
    il/da (nomi risolti via `BaseModel::conCreatoreModificatore()`).
  - Eliminazione utenti non definitiva: cancellare un utente è una cancellazione
    *logica* (`utenti.deleted_at`), non una `DELETE` reale — il record resta nel
    database (storico, integrità referenziale del registro attività) ma
    scompare dagli elenchi e non può più effettuare il login.
- **Registro attività (audit log)**: ogni login/logout e ogni creazione/modifica/
  eliminazione nei moduli admin viene registrata automaticamente — chi (utente),
  cosa (azione, modulo, entità coinvolta) e quando — senza che i controller
  debbano richiamare esplicitamente il logging (vedi `App\Filters\ActivityLogFilter`).
  Consultabile in sola lettura da `Admin > Registro attività` (permesso `log.view`).

## Struttura

```
app/Controllers/
├── AuthController.php          # login, logout
└── Admin/
    ├── DashboardController.php
    ├── UtentiController.php    # CRUD utenti + assegnazione ruolo
    ├── RuoliController.php     # CRUD ruoli + matrice permessi
    └── PermessiController.php  # CRUD permessi

app/Models/        UtenteModel, RuoloModel, PermessoModel, LogAttivitaModel
app/Filters/        AuthFilter, PermissionFilter, CsrfTokenFilter, ActivityLogFilter
app/Views/admin/    index + partial _form/_azioni per ogni modulo (modali)
app/Database/       migrations + RbacSeeder (ruoli/permessi/utente admin iniziali)
public/assets/js/   admin-crud.js — motore generico per le modali CRUD
```

## Setup

```bash
composer install
cp env .env
php spark migrate --all
php spark db:seed RbacSeeder
php spark serve
```

Il database di default è SQLite (`writable/database.db`, zero configurazione). Per
usare MySQL/MariaDB in produzione, modifica le variabili `database.default.*` in
`.env` (vedi i commenti nel file).

Apri `http://localhost:8080/login` con le credenziali demo:

- Email: `admin@example.com`
- Password: `Admin123!`

Il seeder crea anche i ruoli **Operatore** (utenti: view/create/edit) e **Lettore**
(utenti: solo view), utili per verificare il comportamento del RBAC con permessi
ridotti.

## Test

```bash
composer test
# oppure
vendor/bin/phpunit
```

I test girano su un database SQLite in memoria (`Config\Database::$tests`), isolato
dal database di sviluppo — non serve nessuna configurazione aggiuntiva. Coprono:

- **Models** (`tests/unit/Models/`): regole di validazione, `RuoloModel::sincronizzaPermessi()`,
  `PermessoModel::inUso()`, `UtenteModel::findByEmail()`/`conRuolo()`.
- **Filters** (`tests/unit/Filters/`): `AuthFilter` e `PermissionFilter` — redirect,
  risposte 401/403 JSON su richieste AJAX, pass-through quando autorizzato;
  `ActivityLogFilter` — una riga di log per login/logout e per ogni create/update/
  delete nei moduli admin, nessuna riga per le sole letture (index/datatable).
- **Controllers** (`tests/unit/Controllers/`): flusso completo di login/logout via
  feature test (`FeatureTestTrait`), incluse credenziali errate, utente disattivato
  e popolamento dei permessi in sessione; creazione/cancellazione utenti via
  `UtentiController` (`created_by` valorizzato, cancellazione logica non
  definitiva, utente cancellato escluso dalla DataTable).

Nota per chi aggiunge nuovi test con `DatabaseTestTrait`: imposta sempre
`protected $namespace = 'App';` nella classe di test. Senza questa proprietà,
CodeIgniter a volte scopre solo le migration di `Tests\Support` e salta quelle
dell'app, lasciando il database di test senza le tabelle applicative.
