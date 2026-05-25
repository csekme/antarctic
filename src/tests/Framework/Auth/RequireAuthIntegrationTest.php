<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use Exception;
use Framework\Auth\AuthenticatedUser;
use Framework\Auth\RequireAuth;
use Framework\Auth\RequireRole;
use Framework\Dispatcher;
use Framework\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * A Dispatcher::processAnnotation() viselkedését izoláltan teszteljük
 * reflection-on át, hogy ne kelljen a teljes Router + ClassExploder
 * pipeline-t felépíteni.
 */
final class RequireAuthIntegrationTest extends TestCase
{
    public function testRequireAuthAttributeAcceptsAuthenticatedRequest(): void
    {
        $dispatcher = $this->buildDispatcher();

        $reflection = $this->getAttributeReflection(new class {
            #[RequireAuth]
            public function ok(): void {}
        }, 'ok');

        $request = $this->requestFromUser(new AuthenticatedUser(id: 1, roles: ['user']));

        // Nem dob exception-t — siker.
        $this->invokeProcessAnnotation($dispatcher, $reflection, $request);
        $this->assertTrue(true);
    }

    public function testRequireAuthAttributeRejectsAnonymousRequestWithReason(): void
    {
        $dispatcher = $this->buildDispatcher();
        $reflection = $this->getAttributeReflection(new class {
            #[RequireAuth]
            public function ok(): void {}
        }, 'ok');

        $request = $this->requestFromUser(null);
        $request->unauthenticatedReason = 'expired';

        try {
            $this->invokeProcessAnnotation($dispatcher, $reflection, $request);
            $this->fail('expected 401');
        } catch (Exception $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertSame('expired', $e->getMessage());
        }
    }

    public function testRequireAuthFallsBackToGenericReasonWhenNoneRecorded(): void
    {
        $dispatcher = $this->buildDispatcher();
        $reflection = $this->getAttributeReflection(new class {
            #[RequireAuth]
            public function ok(): void {}
        }, 'ok');

        try {
            $this->invokeProcessAnnotation($dispatcher, $reflection, $this->requestFromUser(null));
            $this->fail('expected 401');
        } catch (Exception $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertStringContainsString('Bearer', $e->getMessage());
        }
    }

    public function testRequireRoleAcceptsUserWithRole(): void
    {
        $dispatcher = $this->buildDispatcher();
        $reflection = $this->getAttributeReflection(new class {
            #[RequireRole('admin')]
            public function ok(): void {}
        }, 'ok');

        $request = $this->requestFromUser(new AuthenticatedUser(id: 5, roles: ['admin']));

        $this->invokeProcessAnnotation($dispatcher, $reflection, $request);
        $this->assertTrue(true);
    }

    public function testRequireRoleAcceptsAnyOfMultipleRoles(): void
    {
        $dispatcher = $this->buildDispatcher();
        $reflection = $this->getAttributeReflection(new class {
            #[RequireRole('admin', 'editor')]
            public function ok(): void {}
        }, 'ok');

        $request = $this->requestFromUser(new AuthenticatedUser(id: 5, roles: ['editor']));

        $this->invokeProcessAnnotation($dispatcher, $reflection, $request);
        $this->assertTrue(true);
    }

    public function testRequireRoleRejectsUserMissingRoleWith403(): void
    {
        $dispatcher = $this->buildDispatcher();
        $reflection = $this->getAttributeReflection(new class {
            #[RequireRole('admin')]
            public function ok(): void {}
        }, 'ok');

        $request = $this->requestFromUser(new AuthenticatedUser(id: 5, roles: ['user']));

        try {
            $this->invokeProcessAnnotation($dispatcher, $reflection, $request);
            $this->fail('expected 403');
        } catch (Exception $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testRequireRoleRejectsUnauthenticatedWith401NotEvenChecking403(): void
    {
        $dispatcher = $this->buildDispatcher();
        $reflection = $this->getAttributeReflection(new class {
            #[RequireRole('admin')]
            public function ok(): void {}
        }, 'ok');

        try {
            $this->invokeProcessAnnotation($dispatcher, $reflection, $this->requestFromUser(null));
            $this->fail('expected 401');
        } catch (Exception $e) {
            $this->assertSame(401, $e->getCode(), '401 must precede 403 when no user is present');
        }
    }

    private function buildDispatcher(): Dispatcher
    {
        // A Dispatcher konstruktora Router és PSR-11 ContainerInterface
        // függőséget kér, de a processAnnotation()-ben egyiket sem használja.
        return new Dispatcher(
            $this->createMock(\Framework\Routing\Router::class),
            $this->createMock(\Psr\Container\ContainerInterface::class),
        );
    }

    /**
     * @return \ReflectionAttribute<object>
     */
    private function getAttributeReflection(object $obj, string $method): \ReflectionAttribute
    {
        $reflection = new ReflectionMethod($obj, $method);
        $attributes = $reflection->getAttributes();
        $this->assertNotEmpty($attributes, "Method must have at least one attribute");
        return $attributes[0];
    }

    private function requestFromUser(?AuthenticatedUser $user): Request
    {
        $req = new Request('', 'GET', [], [], [], [], []);
        $req->authUser = $user;
        return $req;
    }

    private function invokeProcessAnnotation(Dispatcher $dispatcher, \ReflectionAttribute $attribute, Request $request): void
    {
        $method = new ReflectionMethod($dispatcher, 'processAnnotation');
        $method->setAccessible(true);
        // A controller_object paraméter most nem releváns; egy üres stub elég.
        $stubController = new class ($request, new \Framework\Response()) extends \Framework\Controller {};
        $method->invoke($dispatcher, $attribute, $stubController, $request);
    }
}
