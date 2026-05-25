<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\TwoFactorChallengeService;
use Lcobucci\JWT\Configuration;
use PHPUnit\Framework\TestCase;

final class TwoFactorChallengeServiceTest extends TestCase
{
    private FrozenClock $clock;
    private TwoFactorChallengeService $service;
    private Configuration $jwt;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $keys = self::generateKeypair();
        $this->jwt = JwtConfigFactory::create([
            'algorithm' => 'RS256',
            'private_key' => $keys['private'],
            'public_key' => $keys['public'],
        ]);
        $this->service = new TwoFactorChallengeService(
            jwt: $this->jwt,
            clock: $this->clock,
            issuer: 'antarctic',
            audience: 'antarctic-spa',
            ttl: 300,
            clockSkew: 5,
        );
    }

    public function testIssueAndVerifyRoundtrip(): void
    {
        $jwt = $this->service->issueChallenge(42);
        $this->assertSame(42, $this->service->verifyChallenge($jwt));
    }

    public function testExpiredChallengeRejected(): void
    {
        $jwt = $this->service->issueChallenge(42);
        $this->clock->set(new DateTimeImmutable('2026-01-01T12:06:00+00:00')); // 6 perc, túl a TTL+skew-n

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(401);
        $this->service->verifyChallenge($jwt);
    }

    public function testMalformedRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(401);
        $this->service->verifyChallenge('not-a-jwt');
    }

    public function testWrongIssuerRejected(): void
    {
        $other = new TwoFactorChallengeService(
            jwt: $this->jwt,
            clock: $this->clock,
            issuer: 'someone-else',
            audience: 'antarctic-spa',
            ttl: 300,
        );
        $jwt = $other->issueChallenge(42);

        $this->expectException(DomainException::class);
        $this->service->verifyChallenge($jwt);
    }

    public function testWrongAudienceRejected(): void
    {
        $other = new TwoFactorChallengeService(
            jwt: $this->jwt,
            clock: $this->clock,
            issuer: 'antarctic',
            audience: 'someone-else',
            ttl: 300,
        );
        $jwt = $other->issueChallenge(42);

        $this->expectException(DomainException::class);
        $this->service->verifyChallenge($jwt);
    }

    public function testAccessTokenCannotBeUsedAsChallenge(): void
    {
        // Manually-built JWT with same keys + iss/aud but NO purpose claim — pl. egy "access" alakú token.
        $now = $this->clock->now();
        $token = $this->jwt->builder()
            ->issuedBy('antarctic')
            ->permittedFor('antarctic-spa')
            ->identifiedBy('abc')
            ->issuedAt($now)
            ->expiresAt($now->add(new DateInterval('PT60S')))
            ->relatedTo('42')
            ->withClaim('roles', ['admin'])
            ->getToken($this->jwt->signer(), $this->jwt->signingKey())
            ->toString();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('not a 2FA challenge');
        $this->service->verifyChallenge($token);
    }

    public function testWrongPurposeClaimRejected(): void
    {
        $now = $this->clock->now();
        $token = $this->jwt->builder()
            ->issuedBy('antarctic')
            ->permittedFor('antarctic-spa')
            ->identifiedBy('abc')
            ->issuedAt($now)
            ->expiresAt($now->add(new DateInterval('PT60S')))
            ->relatedTo('42')
            ->withClaim('purpose', 'password_reset')
            ->getToken($this->jwt->signer(), $this->jwt->signingKey())
            ->toString();

        $this->expectException(DomainException::class);
        $this->service->verifyChallenge($token);
    }

    public function testMissingSubjectRejected(): void
    {
        $now = $this->clock->now();
        $token = $this->jwt->builder()
            ->issuedBy('antarctic')
            ->permittedFor('antarctic-spa')
            ->identifiedBy('abc')
            ->issuedAt($now)
            ->expiresAt($now->add(new DateInterval('PT60S')))
            ->withClaim('purpose', TwoFactorChallengeService::PURPOSE)
            ->getToken($this->jwt->signer(), $this->jwt->signingKey())
            ->toString();

        $this->expectException(DomainException::class);
        $this->service->verifyChallenge($token);
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function generateKeypair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            throw new \RuntimeException('failed to generate test keypair');
        }
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        return ['private' => $private, 'public' => $details['key']];
    }
}
