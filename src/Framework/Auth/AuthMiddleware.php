<?php

declare(strict_types=1);

namespace Framework\Auth;

use DomainException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bearer token parser. Ha érvényes, az `AuthenticatedUser`-t a `user`
 * attribútumba teszi és továbbengedi. Ha hiányzik vagy érvénytelen,
 * **NEM** dobja vissza a kérést — a `#[RequireAuth]` attribútum dolga
 * eldönteni, hogy egy adott endpoint követel-e autentikációt.
 *
 * Az `unauthenticated_reason` attribútum jelzi a hiba okát az upstream
 * komponenseknek (Dispatcher), így a 401 válasz pontos detail mezőt kap.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public const ATTR_USER = 'user';
    public const ATTR_REASON = 'unauthenticated_reason';

    public function __construct(private readonly TokenService $tokenService)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if ($header === '') {
            return $handler->handle($request);
        }

        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return $handler->handle(
                $request->withAttribute(self::ATTR_REASON, 'malformed_authorization_header'),
            );
        }

        try {
            $token = $this->tokenService->verifyAccess($matches[1]);
        } catch (DomainException $e) {
            return $handler->handle(
                $request->withAttribute(self::ATTR_REASON, $e->getMessage()),
            );
        }

        $claims = $token->claims();
        $rolesClaim = $claims->get('roles', []);
        if (!is_array($rolesClaim)) {
            $rolesClaim = [];
        }
        /** @var list<string> $roles */
        $roles = array_values(array_map('strval', $rolesClaim));

        $user = new AuthenticatedUser(
            id: (int) $claims->get('sub'),
            roles: $roles,
            jti: $claims->has('jti') ? (string) $claims->get('jti') : null,
        );

        return $handler->handle($request->withAttribute(self::ATTR_USER, $user));
    }
}
