<?php

declare(strict_types=1);

namespace Framework\Controllers\Api\V1;

use Framework\Controller;
use Framework\Dal;
use Framework\Path;
use Framework\Request;
use Framework\Response;
use PDO;
use Throwable;

/**
 * Kubernetes-style probes.
 *
 *   GET /api/v1/healthz — liveness: process is up, always 200.
 *   GET /api/v1/readyz  — readiness: dependencies are reachable. Pings the DB
 *                         with `SELECT 1`; 503 + problem+json if anything throws.
 *
 * Both endpoints are CSRF-free and unauthenticated; they bypass the rate
 * limit list by virtue of not matching the configured `/api/v1/auth/*` rules
 * and being well under the catch-all `api-default` budget.
 */
class HealthController extends Controller
{
    /** @var callable():PDO */
    private $pdoFactory;

    public function __construct(Request $request, Response $response, array $route_params = [])
    {
        parent::__construct($request, $response, $route_params);
        $this->pdoFactory = static fn (): PDO => Dal::getConnection();
    }

    /**
     * Test/container override hook. Inject a closure returning the PDO to
     * ping; useful for unit tests where we want to simulate a DB failure.
     *
     * @param callable():PDO $factory
     */
    public function setPdoFactory(callable $factory): void
    {
        $this->pdoFactory = $factory;
    }

    #[Path(path: '/api/v1/healthz', method: 'GET')]
    public function liveness(): Response
    {
        return $this->jsonNoStore(['status' => 'ok'], 200);
    }

    #[Path(path: '/api/v1/readyz', method: 'GET')]
    public function readiness(): Response
    {
        try {
            $pdo = ($this->pdoFactory)();
            $pdo->query('SELECT 1');
        } catch (Throwable $e) {
            $body = [
                'type' => 'about:blank',
                'title' => 'Service Unavailable',
                'status' => 503,
                'detail' => 'A required dependency is unreachable.',
                'instance' => '/api/v1/readyz',
                'checks' => ['database' => 'fail'],
            ];
            $response = Response::json($body, 503);
            $response->addHeader('Content-Type: application/problem+json; charset=utf-8');
            $response->addHeader('Cache-Control: no-store');
            return $response;
        }

        return $this->jsonNoStore([
            'status' => 'ready',
            'checks' => ['database' => 'ok'],
        ], 200);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonNoStore(array $payload, int $status): Response
    {
        $response = Response::json($payload, $status);
        $response->addHeader('Cache-Control: no-store');
        return $response;
    }
}
