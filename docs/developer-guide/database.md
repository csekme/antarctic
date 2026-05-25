# Adatbázis: migrációk és repositoryk

Az Antarctic az adatbázis-séma kezelésére a [`doctrine/migrations`](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.7/index.html) (3.7+), a query-réteghez PDO-injektált repository osztályokat használ. A legacy `AbstractUser` és `TwoFactorModel` static finder metódusok továbbra is elérhetők BC-kompatibilitás miatt, de új kód a repositorykat használja.

## Mi van a `db/migrations/`-ban

```
db/migrations/
├── Version20260525_010000_CreateUserTable.php
├── Version20260525_010100_CreateRoleAndUserRole.php
├── Version20260525_010200_CreateTwoFactorTable.php
└── Version20260525_010300_CreateRefreshTokens.php
```

Mindegyik az `AbstractMigration`-t implementálja, és platform-független `Schema` API-val ír (sqlite, MariaDB és PostgreSQL támogatott).

## CLI használat

A `src/migrations.php` és `src/migrations-db.php` config-fájlok automatikusan beolvasódnak, ha a `src/` mappából futtatod a CLI-t:

```bash
cd src
vendor/bin/doctrine-migrations migrations:status     # függő migrációk
vendor/bin/doctrine-migrations migrations:migrate    # alkalmazás
vendor/bin/doctrine-migrations migrations:generate   # új skeleton
```

## Új migration írása

```bash
vendor/bin/doctrine-migrations migrations:generate
# → létrejön egy üres VersionYYYYMMDD_HHMMSS_*.php skeleton
```

Tipikus tartalom:

```php
namespace Db\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525_120000_AddUserPhoneColumn extends AbstractMigration
{
    public function getDescription(): string { return 'Add phone column to user table.'; }

    public function up(Schema $schema): void
    {
        $schema->getTable('user')->addColumn('phone', 'string', ['length' => 32, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('user')->dropColumn('phone');
    }
}
```

## Repository réteg

A `Framework\Repositories\` namespace alatt élnek a PDO-injektált repository-osztályok:

| Repository | Mit ad |
|---|---|
| `UserRepository` | `findById`, `findByEmail`, `findByUsername`, `findByUuid`, `getRoles(int $id): list<string>` |
| `TwoFactorRepository` | `findByUserId`, `findByUserIdAndMethod`, `enabledMethods`, `enroll`, `setEnabled` |
| `Framework\Auth\RefreshTokenRepository` | `store`, `findByHash`, `markRotated`, `revokeFamily`, `purgeExpired` |

A PDO példányt a [Container és DI](container.md) oldalon leírt PSR-11 container injektálja automatikusan:

```php
namespace Application\Services;

use Framework\Repositories\UserRepository;

final class GreetingService
{
    public function __construct(private readonly UserRepository $users) {}

    public function welcome(int $userId): string
    {
        $user = $this->users->findById($userId);
        return $user ? "Hello, {$user->firstname}!" : 'Hello, stranger!';
    }
}
```

A `$container->get(GreetingService::class)` automatikusan `new GreetingService(new UserRepository($pdo))`-t hív.

## Tesztelés sqlite-tal

A repository-tesztek (lásd `tests/Framework/Repositories/`) sqlite in-memory adatbázist használnak — gyors, nincs külső függőség. A `MigrationsTest` ugyanezzel a setup-pal végigfuttatja az összes migration-t.

```php
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, ...);');

$repo = new UserRepository($pdo);
```

## A repository réteg határai

- **Insert / update entityk** — a `UserRepository` és `TwoFactorRepository` írásra a session-flow-hoz szükséges műveleteket adja (`enroll`, `setEnabled`); általános profil-update az alkalmazás-szintű service-rétegen keresztül szervezhető.
- **Entity-hydratálás** — a `TwoFactorRepository` array-rekordokkal dolgozik. Ha service-layer-ben entity-példányokra van szükség, a repositoryban hozzáadható egy hydrate metódus.
- **Tranzakció-management** — a repositoryk single-statement műveleteket csinálnak. Több művelet összevonása (transactional service) a service réteg felelőssége; a `Dal::getConnection()` PDO példánya közvetlenül `beginTransaction()`-elhető.

## Lásd még

- [`doctrine/migrations` dokumentáció](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.7/)
- [Container és DI](container.md) — hogyan injektálódik a PDO és a repository automatikusan
- [Tesztelés](testing.md) — sqlite in-memory pattern
