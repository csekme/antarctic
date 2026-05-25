<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use DateTimeImmutable;
use Framework\Auth\AuthMiddleware;
use Framework\Auth\AuthenticatedUser;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\RefreshTokenRepository;
use Framework\Auth\TokenService;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddlewareTest extends TestCase
{
    private TokenService $tokens;

    /** @var array{request: ?ServerRequestInterface} */
    private array $captured = ['request' => null];
    private RequestHandlerInterface $captureHandler;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE refresh_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, family_id TEXT, token_hash TEXT UNIQUE, rotated_from INTEGER, expires_at TEXT, revoked_at TEXT, user_agent TEXT, ip TEXT, created_at TEXT)');

        $keys = self::generateKeypair();
        $config = JwtConfigFactory::create([
            'algorithm' => 'RS256',
            'private_key' => $keys['private'],
            'public_key' => $keys['public'],
        ]);
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $this->tokens = new TokenService(
            jwt: $config,
            refreshTokens: new RefreshTokenRepository($pdo),
            clock: $clock,
            issuer: 'antarctic',
            audience: 'antarctic-spa',
            accessTtl: 900,
            refreshTtl: 3600,
        );

        $this->captureHandler = new class($this->captured) implements RequestHandlerInterface {
            public function __construct(private array &$captured) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured['request'] = $request;
                return new Response(200);
            }
        };
    }

    public function testNoAuthorizationHeaderPassesThroughUnchanged(): void
    {
        $middleware = new AuthMiddleware($this->tokens);

        $middleware->process(new ServerRequest('GET', '/api/v1/me'), $this->captureHandler);

        $captured = $this->captured['request'];
        $this->assertNotNull($captured);
        $this->assertNull($captured->getAttribute(AuthMiddleware::ATTR_USER));
        $this->assertNull($captured->getAttribute(AuthMiddleware::ATTR_REASON));
    }

    public function testValidBearerTokenSetsAuthenticatedUserAttribute(): void
    {
        $jwt = $this->tokens->issueAccessToken(userId: 42, roles: ['admin']);
        $middleware = new AuthMiddleware($this->tokens);

        $request = (new ServerRequest('GET', '/api/v1/me'))
            ->withHeader('Authorization', 'Bearer ' . $jwt);

        $middleware->process($request, $this->captureHandler);

        $user = $this->captured['request']->getAttribute(AuthMiddleware::ATTR_USER);
        $this->assertInstanceOf(AuthenticatedUser::class, $user);
        $this->assertSame(42, $user->id);
        $this->assertSame(['admin'], $user->roles);
        $this->assertNotNull($user->jti);
    }

    public function testMalformedHeaderRecordsReasonInsteadOfRejecting(): void
    {
        $middleware = new AuthMiddleware($this->tokens);

        $request = (new ServerRequest('GET', '/api/v1/me'))
            ->withHeader('Authorization', 'Token xyz');

        $response = $middleware->process($request, $this->captureHandler);

        $this->assertSame(200, $response->getStatusCode(), 'middleware never rejects');
        $reason = $this->captured['request']->getAttribute(AuthMiddleware::ATTR_REASON);
        $this->assertSame('malformed_authorization_header', $reason);
    }

    public function testInvalidTokenRecordsReason(): void
    {
        $middleware = new AuthMiddleware($this->tokens);

        $request = (new ServerRequest('GET', '/api/v1/me'))
            ->withHeader('Authorization', 'Bearer not-a-valid-jwt');

        $middleware->process($request, $this->captureHandler);

        $reason = $this->captured['request']->getAttribute(AuthMiddleware::ATTR_REASON);
        $this->assertIsString($reason);
        $this->assertNotSame('', $reason);
        $this->assertNull($this->captured['request']->getAttribute(AuthMiddleware::ATTR_USER));
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function generateKeypair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        return ['private' => $private, 'public' => $details['key']];
    }
}
