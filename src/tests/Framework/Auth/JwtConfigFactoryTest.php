<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use Framework\Auth\JwtConfigFactory;
use Lcobucci\JWT\Configuration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JwtConfigFactoryTest extends TestCase
{
    public function testCreatesConfigFromInlinePemKeys(): void
    {
        $keys = $this->generateKeypair();

        $config = JwtConfigFactory::create([
            'algorithm' => 'RS256',
            'private_key' => $keys['private'],
            'public_key' => $keys['public'],
        ]);

        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testCreatesConfigFromKeyFilePaths(): void
    {
        $keys = $this->generateKeypair();
        $privatePath = tempnam(sys_get_temp_dir(), 'jwt-priv-');
        $publicPath = tempnam(sys_get_temp_dir(), 'jwt-pub-');
        file_put_contents($privatePath, $keys['private']);
        file_put_contents($publicPath, $keys['public']);

        try {
            $config = JwtConfigFactory::create([
                'algorithm' => 'RS256',
                'private_key_path' => $privatePath,
                'public_key_path' => $publicPath,
            ]);
            $this->assertInstanceOf(Configuration::class, $config);
        } finally {
            unlink($privatePath);
            unlink($publicPath);
        }
    }

    public function testRejectsUnsupportedAlgorithm(): void
    {
        $this->expectException(RuntimeException::class);
        JwtConfigFactory::create(['algorithm' => 'HS512']);
    }

    public function testRejectsMissingKeyMaterial(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWT private key');
        JwtConfigFactory::create(['algorithm' => 'RS256']);
    }

    /**
     * @return array{private: string, public: string}
     */
    private function generateKeypair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        return ['private' => $private, 'public' => $details['key']];
    }
}
