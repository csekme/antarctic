# JWT kulcsok kezelése

Az Antarctic RS256 aszimmetrikus aláírást használ. Két fájlra van szükséged:

- **`jwt-private.pem`** — ezzel írja alá a backend a tokeneket. **Titok.** Sosem érheti el a kliens.
- **`jwt-public.pem`** — ezzel verifikálja a backend a beérkező tokeneket. Akár publikus is lehet (de általában a backend lokálisan tárolja).

## Generálás

A keretrendszer hozza a `keys:generate` console parancsot:

```bash
cd src
bin/console keys:generate
```

Default kimenet:

```
Generating RSA-4096 keypair…
  private: /Users/.../src/var/keys/jwt-private.pem (mode 0600)
  public : /Users/.../src/var/keys/jwt-public.pem (mode 0644)

Done. Add these to .env for production:
  JWT_PRIVATE_KEY_PATH=/.../src/var/keys/jwt-private.pem
  JWT_PUBLIC_KEY_PATH=/.../src/var/keys/jwt-public.pem
```

## Argumentumok

| Argumentum | Default | Jelentés |
|---|---|---|
| `--bits=N` | 4096 | RSA modulus mérete. **Ne menj 2048 alá.** |
| `--out=PREFIX` | `var/keys/jwt` | Output prefix — `<prefix>-private.pem` és `<prefix>-public.pem` jön létre. |
| `--force` | (nincs) | Felülírja a meglévő kulcsokat. Nélküle a parancs leáll, ha létezik már fájl. |

Példák:

```bash
# Production-szintű 4096 bit, default helyre
bin/console keys:generate

# Tesztkulcsok gyorsan
bin/console keys:generate --bits=2048 --out=/tmp/test-jwt

# Régi kulcsok cseréje (új deploy)
bin/console keys:generate --force
```

## Fájl jogosultságok

A parancs automatikusan beállítja:

- **`jwt-private.pem` → `0600`** (csak az owner olvashatja). Bármi mást ellenőrző tool (pl. SSH) fenyeget.
- **`jwt-public.pem` → `0644`** (mindenki olvashatja, csak owner írhatja).

A `var/keys/` mappa `0700` jogosultsággal jön létre.

!!! warning "Soha ne commitold a `var/keys/`-t"
    A `.gitignore`-ban legyen `/src/var/`. Production kulcsokat **mindig env-változón vagy secret managerből** tölts (lásd lent), ne fájlrendszerből.

## Hogy használd a kulcsokat

A `config/jwt.php` két forrásból olvas:

1. **Env-változó (inline PEM tartalom)** — production preferált.
2. **Fájl-elérési út** — development preferált.

### Development (.env)

```bash
JWT_PRIVATE_KEY_PATH=/Users/csk/Projects/antarctic/src/var/keys/jwt-private.pem
JWT_PUBLIC_KEY_PATH=/Users/csk/Projects/antarctic/src/var/keys/jwt-public.pem
```

### Production (env-változó vagy K8s secret)

A PEM tartalmát egyben dobod be env-be:

```bash
export JWT_PRIVATE_KEY="$(cat /secure/path/jwt-private.pem)"
export JWT_PUBLIC_KEY="$(cat /secure/path/jwt-public.pem)"
```

Vagy K8s secret:

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: antarctic-jwt
type: Opaque
stringData:
  JWT_PRIVATE_KEY: |
    -----BEGIN PRIVATE KEY-----
    MIIE...
    -----END PRIVATE KEY-----
  JWT_PUBLIC_KEY: |
    -----BEGIN PUBLIC KEY-----
    MIIBI...
    -----END PUBLIC KEY-----
```

A pod `env` szakaszában:

```yaml
env:
  - name: JWT_PRIVATE_KEY
    valueFrom:
      secretKeyRef: { name: antarctic-jwt, key: JWT_PRIVATE_KEY }
  - name: JWT_PUBLIC_KEY
    valueFrom:
      secretKeyRef: { name: antarctic-jwt, key: JWT_PUBLIC_KEY }
```

### Jelszó-védett private key

Ha a kulcsot jelszóval védted (`openssl rsa -aes256 …`), tedd a jelszót env-be:

```bash
JWT_PRIVATE_KEY_PASSPHRASE=...
```

A `keys:generate` jelenleg nem ír jelszó-védett kulcsot — manuálisan kell rátenni:

```bash
openssl rsa -aes256 -in jwt-private.pem -out jwt-private-encrypted.pem
```

## Kulcsrotáció

A `family_id` és `jti` mechanizmusok lehetővé teszik, hogy futás közben váltson a backend új kulcsra **anélkül**, hogy minden user kijelentkezne. A teljes folyamat:

1. **Új kulcspár generálása** különálló néven: `bin/console keys:generate --out=var/keys/jwt-v2`.
2. **A new public key párhuzamos verifikálása** — két public key töltése a `JwtConfigFactory`-be. *(Jelenleg single-key — multi-key rotation később.)*
3. **Az új private key élesítése** — config csere a `Bootstrap.php`-ban.
4. **24-48 órás overlap** alatt a régi tokenek verifikálódnak a régi public-szal, az újak az újjal.
5. **A régi kulcs eldobása**, amikor az utolsó access token is lejárt.

!!! info "Jelenleg single-key"
    Az M2.a egy aktív kulcsot támogat. A multi-key rotation egy későbbi PR hatóköre (M5 nem szállította; helyette a production hardening került szállításra).

## Tesztelés

A tesztekben ne fájlból tölts kulcsot — generálj on-the-fly:

```php
$resource = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
openssl_pkey_export($resource, $private);
$details = openssl_pkey_get_details($resource);

$config = JwtConfigFactory::create([
    'algorithm' => 'RS256',
    'private_key' => $private,
    'public_key' => $details['key'],
]);
```

Példa: [`tests/Framework/Auth/TokenServiceTest.php`](https://github.com/csekme/antarctic/blob/main/src/tests/Framework/Auth/TokenServiceTest.php).
