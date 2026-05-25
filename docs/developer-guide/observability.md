# Observability

Az M5 óta minden request korrellálható trace ID-vel, és minden log struktúrált JSON-ben megy a stdout-ra. K8s probe-ok két dedikált endpoint-tal pingelhetők.

## Trace ID — request korreláció

A `Framework\Http\TraceIdMiddleware` minden request elején:

1. Ha érkezett `X-Request-Id` header (load balancer / API gateway state), és átmegy a whitelist regex-en (`[A-Za-z0-9_.-]{1,128}`), átveszi.
2. Egyébként generál egy 32 karakteres hex tokent (`bin2hex(random_bytes(16))`).

A trace ID három helyen érhető el:

| Hely | Hogyan |
|---|---|
| Request attribute | `$request->getAttribute('traceId')` |
| Static holder | `Framework\Logging\TraceIdHolder::get()` |
| Response header | `X-Request-Id: <id>` |

A `TraceIdHolder` egy process-szintű holder; long-running workerben (RoadRunner, ReactPHP) a middleware `finally` blokkjában automatikusan resetelődik.

## Strukturált JSON log

A `Framework\Logging\LoggerFactory::fromEnv()` egy Monolog logger-t épít, ami:

- `php://stdout`-ra ír (12-factor),
- JSON sorokba formattálva (`JsonFormatter`, `BATCH_MODE_NEWLINES`),
- minden record-ra rátesz egy `extra.trace_id` mezőt (`TraceIdProcessor`),
- a PSR-3 message placeholder-eket helyettesíti (`PsrLogMessageProcessor`).

Példa output:

```json
{"message":"login failed","context":{"email":"a@b.com"},"level":400,"level_name":"ERROR","channel":"app","datetime":"2026-05-25T10:23:45+00:00","extra":{"trace_id":"a1b2c3d4..."}}
```

### Env változók

| Env | Default | Hatás |
|---|---|---|
| `APP_LOG_CHANNEL` | `app` | A Monolog channel name |
| `APP_LOG_LEVEL` | `INFO` | `DEBUG` \| `INFO` \| `NOTICE` \| `WARNING` \| `ERROR` \| `CRITICAL` \| `ALERT` \| `EMERGENCY` |

A `ErrorHandlerMiddleware` a fenti loggert kapja Bootstrap-ben: minden 5xx exception loggolva lesz a trace ID-vel együtt.

## Healthcheck endpoint-ok

A `Framework\Controllers\Api\V1\HealthController` két k8s-stílusú probe-ot szolgáltat:

| Endpoint | Cél | Visszatérés |
|---|---|---|
| `GET /api/v1/healthz` | Liveness (process up) | Mindig `200 {"status":"ok"}` |
| `GET /api/v1/readyz` | Readiness (függőségek elérhetők) | `200 {"status":"ready","checks":{"database":"ok"}}` ha PDO `SELECT 1` átmegy; `503` + problem+json egyébként |

Mindkettő `Cache-Control: no-store`-ral megy ki, a `readyz` hibája `application/problem+json` formátumú (RFC 7807).

### k8s probe példa

```yaml
livenessProbe:
  httpGet:
    path: /api/v1/healthz
    port: 80
  initialDelaySeconds: 10
  periodSeconds: 30

readinessProbe:
  httpGet:
    path: /api/v1/readyz
    port: 80
  initialDelaySeconds: 5
  periodSeconds: 10
```

A `HttpsRedirectMiddleware` excluded prefix listája ezt a két útvonalat plain HTTP-n is átengedi, hogy a kubelet a pod IP-n (HTTP-only) is tudja pingelni redirect nélkül.

## Log korreláció gyakorlatban

Loki / ELK / Datadog felé:

```logql
# Egy adott request összes log sora:
{app="antarctic"} | json | trace_id="a1b2c3d4..."

# A kliens a hibajelentésben ugyanazt az ID-t küldi:
curl -i https://api.example.com/api/v1/me
# < X-Request-Id: a1b2c3d4...
```

Ha a kliens megkapja a `X-Request-Id` header-t, érdemes a hibajelentő felületén feltüntetni — egy kattintás a log aggregátorban → komplett trace.
