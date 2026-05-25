<?php

declare(strict_types=1);

namespace Framework\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Root OpenAPI metadata. The {@see OpenApiGenerator} scans for {@see OA\OpenApi}
 * / {@see OA\Info} / {@see OA\SecurityScheme} attributes, so collecting them on
 * a dedicated empty class keeps the controllers and DTOs free of top-level
 * boilerplate.
 *
 * The base path and API version are intentionally hard-coded — they reflect
 * the current versioning scheme (`/api/v1`). When `v2` lands, this class
 * forks per-version.
 */
#[OA\OpenApi(
    info: new OA\Info(
        version: '1.0',
        title: 'Antarctic API',
        description: 'SPA-native JWT-secured PHP backend. See /api/v1/docs for the Swagger UI.',
    ),
    servers: [
        new OA\Server(url: '/', description: 'Same-origin (drop-in SPA or local dev).'),
    ],
    security: [
        ['bearerAuth' => []],
    ],
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'RS256-signed access token issued by `POST /api/v1/auth/login`. Send as `Authorization: Bearer <token>`.',
)]
final class OpenApiInfo
{
}
