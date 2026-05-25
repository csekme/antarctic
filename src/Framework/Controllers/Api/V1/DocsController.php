<?php

declare(strict_types=1);

namespace Framework\Controllers\Api\V1;

use Framework\Controller;
use Framework\OpenApi\OpenApiGenerator;
use Framework\Path;
use Framework\Response;

/**
 * Self-documentation endpoints.
 *
 *   GET /api/v1/docs.json — OpenAPI 3.x JSON spec (always available).
 *   GET /api/v1/docs      — Swagger UI HTML (dev only; production returns 404).
 *
 * The JSON is sourced from {@see OpenApiGenerator}, which prefers a precompiled
 * `var/cache/openapi.json` and falls back to an in-process scan. The Swagger
 * UI loads its assets from the unpkg CDN — no local UI bundling.
 */
class DocsController extends Controller
{
    private OpenApiGenerator $generator;
    private bool $uiEnabled;

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->generator = OpenApiGenerator::forSource(
            sourceRoot: defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 4),
            cacheFile: (defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 4)) . '/var/cache/openapi.json',
        );
        $this->uiEnabled = (getenv('APP_ENV') ?: 'production') !== 'production';
    }

    /** Test/container override hook. */
    public function setGenerator(OpenApiGenerator $generator): void
    {
        $this->generator = $generator;
    }

    /** Test/container override hook. */
    public function setUiEnabled(bool $enabled): void
    {
        $this->uiEnabled = $enabled;
    }

    #[Path(path: '/api/v1/docs.json', method: 'GET')]
    public function json(): Response
    {
        $body = $this->generator->toJson();
        $response = new Response();
        $response->setStatusCode(200);
        $response->setBody($body);
        $response->addHeader('Content-Type: application/json; charset=utf-8');
        $response->addHeader('Cache-Control: no-store');
        return $response;
    }

    #[Path(path: '/api/v1/docs', method: 'GET')]
    public function ui(): Response
    {
        if (!$this->uiEnabled) {
            $response = Response::json([
                'type' => 'about:blank',
                'title' => 'Not Found',
                'status' => 404,
                'detail' => 'Swagger UI is disabled in this environment.',
            ], 404);
            $response->addHeader('Content-Type: application/problem+json; charset=utf-8');
            return $response;
        }

        $response = new Response();
        $response->setStatusCode(200);
        $response->setBody($this->renderSwaggerHtml());
        $response->addHeader('Content-Type: text/html; charset=utf-8');
        $response->addHeader('Cache-Control: no-store');
        return $response;
    }

    private function renderSwaggerHtml(): string
    {
        $title = 'Antarctic API — Swagger UI';
        // The unpkg URLs are pinned to a major version. The Swagger UI bundle
        // is loaded entirely from the CDN; we ship no static assets.
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{$title}</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.addEventListener('load', () => {
      window.ui = SwaggerUIBundle({
        url: '/api/v1/docs.json',
        dom_id: '#swagger-ui',
        deepLinking: true,
      });
    });
  </script>
</body>
</html>
HTML;
    }
}
