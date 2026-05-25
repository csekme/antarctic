# Pagination konvenció

Minden list/index endpoint **ugyanazt** a query-shape-t fogadja és **ugyanazt** a válasz-envelope-ot adja. Így a React kliens egyetlen `<PaginationControls>` komponenst használhat az egész admin-felülethez, és az OpenAPI generálás is konzisztens schema-referenciákat hoz.

## Query params

| Param | Default | Jelentés |
|---|---|---|
| `page` | `1` | 1-alapú oldalszám |
| `perPage` | `20` | Tételek oldalankéet, [1..`maxPerPage`] tartományba szorítva (default `maxPerPage=100`) |
| `sort` | (üres) | `-createdAt,+name,email` — vesszővel elválasztott `[+|-]<mező>` lista. A `-` prefix `DESC`, `+` (vagy hiányzó prefix) `ASC`. |
| `filter[<kulcs>]` | (üres) | Asszociatív filter-map, pl. `filter[status]=active&filter[role]=admin`. Az endpoint a saját kulcs-whitelist-jét érvényesíti. |

Példa: `GET /api/v1/users?page=2&perPage=50&sort=-createdAt&filter[status]=active`.

A hibás bemenet (negatív `page`, túl magas `perPage`, érvénytelen sort-identifier stb.) ugyanazon a 422-es csatornán érkezik vissza, mint a DTO-validáció — RFC 7807 problem+json `errors` mezővel.

## Response envelope

```json
{
  "data": [ /* az endpoint-specifikus rekordok listája */ ],
  "meta": {
    "page": 2,
    "perPage": 50,
    "total": 137,
    "totalPages": 3
  }
}
```

- `data` mindig **lista**, akkor is, ha egy elem van — az endpoint nem ad vissza nyers objektumot.
- `meta` mindig **objektum** a négy mezővel; üres halmaz esetén `total = 0`, `totalPages = 0`.

## Egy endpoint kódja

```php
use Framework\Pagination\PaginationParams;
use Framework\Pagination\Page;

#[Path(path: '/api/v1/users', method: 'GET')]
#[RequireAuth]
public function list(): Response
{
    $params = PaginationParams::fromQuery($this->request->get);
    $total = $this->users->countMatching($params->filter);
    $rows  = $this->users->findMatching(
        $params->filter,
        $params->sort,
        $params->perPage,
        $params->offset(),
    );
    return Response::json(Page::of($rows, $total, $params)->toArray());
}
```

Repository oldalon `$params->filter` kulcsait whitelistelni kell — a `PaginationParams` parser csak az alak-helyességet érvényesíti (scalar érték, string kulcs), nem a tartalmat.

## Sort biztonság

A `sort` parser identifier-regex-szel szűri a mezőneveket (`^[A-Za-z_][A-Za-z0-9_.]*$`), így nyers `?sort=-id;DROP TABLE` típusú támadás nem juthat tovább. Ez azonban **nem helyettesíti** a repository-szintű whitelist-et — az adott táblán nem létező mezőre rendezés a repository felelőssége.

Ajánlott minta a repositoryban:

```php
private const SORTABLE = ['id', 'createdAt', 'email', 'username'];

public function findMatching(array $filter, array $sort, int $limit, int $offset): array
{
    $orderBy = [];
    foreach ($sort as $s) {
        if (!in_array($s->field, self::SORTABLE, true)) {
            continue; // némán dobja az ismeretlen mezőket
        }
        $orderBy[] = sprintf('%s %s', $s->field, $s->isDescending() ? 'DESC' : 'ASC');
    }
    // ...
}
```

## A réteg határai

- **Cursor-based pagination** — a beépített konvenció page-offset alapú. Cursor (`next_cursor` mező a `meta`-ban) nagy táblákon kiegészítésként hozzáadható az adott endpointhoz.
- **OpenAPI `Page<T>` schema generálás** — a swagger-php nem támogatja a generikus templating-et; konkrét endpoint-szintű schema-deklarációkkal (`UserListResponse extends Page` minta) dokumentáld a típusokat.
- **Stream / NDJSON** — a `Page<T>` egyetlen array-envelope; nagyobb halmazokra eltérő endpoint-mintát érdemes választani.
