# Karbantartói útmutató

Ez az oldal magának a fejlesztői guide-nak a karbantartási szabályait írja le. Olvasd el, mielőtt új oldalt hozol létre vagy nagyobb átszervezést indítasz.

## A guide célja és határa

| Mi VAN itt | Mi NINCS itt |
|---|---|
| **Hogyan használd** az Antarctic-ot egy alkalmazás építéséhez. | A keretrendszer belső, fejlesztőknek szóló terve. (Lásd `PLAN.md`.) |
| A **jelenlegi** API és viselkedés. | "Milyen volt a múlt héten?" — a milestone history (`docs/m{n}.md`) erre van. |
| Konkrét példák, kódminták, konvenciók. | Kötelező-tervezési doksik, ADR-ek. (Külön `docs/decisions/` lehetne, ha kell.) |
| Forward-pointing megjegyzések, ha egy közeli PR változtat valamit (használj `!!! note` admonition-t). | Spekulatív, határozatlan idejű "talán majd". |

## Mikor írj / frissíts ide

**Mindig**, amikor egy PR megváltoztatja a fejlesztők számára látható felületet:

- Új middleware → `http/middleware.md` referencia-tábla + saját oldal ha komplex.
- Új config kulcs vagy fájl → `configuration.md`.
- Új attribútum (`#[…]`) → új oldal vagy `http/routing.md` kiterjesztés.
- Új CLI parancs → új `cli/<command>.md` oldal.
- Új konvenciós minta (pl. exception hierarchia) → vonatkozó oldalon példával.
- Visszafelé-inkompatibilis változás → mindenki érintett oldal frissítése + `!!! warning` admonition.

**Ne** írj ide:

- "Mi változott ebben a PR-ben" típusú lista — az a `docs/m{n}.md`-ben.
- Tervek, brainstorming, "talán" — azok PLAN.md-ben vagy issue-kban.
- Code review észrevétel — az a PR commentekben.

## Stílus

### Hang és nyelv

- **Magyar** — a felhasználói nyelvével egyezően.
- **Tegező** alakzat, közvetlen ("hozz létre", "olvasd", "használd").
- **Aktív hang**: "A middleware visszaad egy választ", nem "egy válasz visszaadásra kerül".
- **Rövid mondatok**. Egy bekezdés egy gondolat.

### Sruktúra

- Minden oldal H1 címmel kezdődik (egyetlen H1 / fájl).
- H2 a fő szekciókra, H3 alszekciókra.
- Kerüld a 3 szintnél mélyebb hierarchiát — bontsd külön oldalra.
- A bevezető 1-3 mondat: ki ez, mit csinál, hova illeszkedik.

### Kódminták

- Mindig **futtatható** vagy legalább helyesen szintaktikus kód.
- A nyelvet (`php`, `json`, `bash`, `yaml`) **mindig** jelöld a code fence után.
- Hosszabb minták előtt 1 mondat kontextus.
- Path-eket [link](http://example.com) szintaxissal jelöld, ha a guide-on belüli.

### Admonition-ok

A `!!! type "Cím"` az MkDocs Material szintaxisa.

```markdown
!!! info "Készülő változás"
    Egy következő verzióban átveszi a JWT modul.

!!! warning "Production-ben"
    Soha ne állítsd `true`-ra.

!!! tip "Pro tipp"
    Használj saját exception hierarchiát.

!!! note "Megjegyzés"
    Ez a viselkedés még finomításra szorulhat.
```

Mértékkel — minden oldalon 1-3 admonition az ideális. Ha sok van, valószínűleg a fő szövegnek kéne erősebbnek lennie.

## Új oldal hozzáadása

1. **Hozd létre** a markdown fájlt a megfelelő alkönyvtár alá. Naming: kebab-case (`route-cache.md`, nem `routeCache.md`).
2. **Add hozzá** a [`mkdocs.yml`](../../../mkdocs.yml) `nav:` szakaszához. A sorrend logikus legyen (általánostól specifikusig, vagy függőség-irányban).
3. **Linkeld be** a vonatkozó indextől / áttekintő oldalakról. (Ha az `index.md`-ből nem érhető el, valószínűleg nem fontos.)
4. **Ellenőrizd** local rendererrel ha tudsz:
   ```bash
   pip install mkdocs-material
   mkdocs serve   # http://127.0.0.1:8000
   ```

## Mappastruktúra

A jelenlegi struktúra:

```
docs/developer-guide/
├── index.md                   # Landing + ToC + milestone status
├── getting-started.md         # First-app tutorial
├── architecture.md            # High-level overview
├── configuration.md           # .env + application.json + config/*.php
├── testing.md                 # PHPUnit + middleware testing patterns
├── http/                      # HTTP layer (külön mappa, ≥4 oldal)
│   ├── index.md
│   ├── middleware.md
│   ├── routing.md
│   ├── request-response.md
│   ├── cors.md
│   └── error-handling.md
└── _meta/
    └── MAINTAINING.md         # Ez az oldal
```

Új mappát **akkor** hozz létre, ha legalább 3 különálló oldal lesz benne (pl. `auth/`, `http/`).

## Konzisztencia checklist új oldalra

- [ ] H1 cím, ami megegyezik a `mkdocs.yml`-ben szereplővel.
- [ ] Első bekezdés: "ki vagyok, mit csinálok".
- [ ] Minimum 1 példa-kódminta.
- [ ] Tervezett, de még nem szállított funkciók `!!! note`-tal jelölve (ne hagyd hidden assumption-ben).
- [ ] Kapcsolódó oldalakra inline link (markdown `[szöveg](path.md)`).
- [ ] A `mkdocs.yml` `nav:` listájához hozzáadva.
- [ ] Referencia-blokk az oldal alján a kapcsolódó fájlokra a forráskódban.

## Milestone history vs. developer guide

Egy fontos elkülönítés:

| `docs/m{n}.md` (milestone history) | `docs/developer-guide/*.md` |
|---|---|
| **Időpontra van fagyasztva** — egy PR állapotát írja le. | **Élő** — a jelenlegi állapotot tükrözi. |
| Új fájlok / módosítások / verifikáció lista. | Konceptuális, használat-orientált magyarázat. |
| Hossza limitált (1-2 oldal / milestone). | Annyi, amennyi a téma kifejtéséhez kell. |
| Auditra használt — "mit szállítottunk?" | Tanulásra használt — "hogy működik?" |

Tehát egy CORS PR után:

1. **m{n}.md**: a milestone-jegyzőkönyv kap egy bejegyzést — "CORS middleware hozzáadva, fájlok X, Y, Z, tesztek N db".
2. **http/cors.md**: a CORS middleware használata, konfig, példák, tesztelés.

A két fájl tartalma **NEM** duplikálódik.

## Render és deploy

A `mkdocs.yml` a `docs/developer-guide` mappát rendereli. Lokális render:

```bash
pip install mkdocs mkdocs-material
mkdocs serve            # dev szerver localhost:8000-en
mkdocs build            # statikus HTML a site/ alá
```

Production deploy javaslat:

- **GitHub Pages** — `mkdocs gh-deploy` egy CI lépésben push után.
- **Static hosting** (Netlify, Cloudflare Pages, S3) — `mkdocs build` és a `site/` mappa szinkronizálása.

A `site/` mappa **gitignored** legyen a repo gyökerében.
