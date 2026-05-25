<?php

declare(strict_types=1);

namespace Framework\Auth;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use RuntimeException;

/**
 * Lcobucci\JWT\Configuration példányt épít az alkalmazás-konfigurációból.
 *
 * Az algoritmus jelenleg RS256 (asymmetric). A kulcsok két forrásból
 * jöhetnek: env-ből inline (PEM tartalom) vagy fájl-elérési útból. Ha
 * mindkettő üres, kivételt dob — production-ben nem akarunk csendben
 * hardcoded fallback-re esni.
 */
final class JwtConfigFactory
{
    /**
     * @param array{
     *   algorithm: string,
     *   private_key?: ?string,
     *   public_key?: ?string,
     *   private_key_path?: ?string,
     *   public_key_path?: ?string,
     *   private_key_passphrase?: string,
     * } $config
     */
    public static function create(array $config): Configuration
    {
        $algorithm = strtoupper($config['algorithm'] ?? 'RS256');
        if ($algorithm !== 'RS256') {
            throw new RuntimeException(sprintf('Unsupported JWT algorithm "%s".', $algorithm));
        }

        return Configuration::forAsymmetricSigner(
            new Sha256(),
            self::loadKey(
                $config['private_key'] ?? null,
                $config['private_key_path'] ?? null,
                $config['private_key_passphrase'] ?? '',
                'private',
            ),
            self::loadKey(
                $config['public_key'] ?? null,
                $config['public_key_path'] ?? null,
                '',
                'public',
            ),
        );
    }

    private static function loadKey(?string $inline, ?string $path, string $passphrase, string $label): Key
    {
        if ($inline !== null && $inline !== '') {
            return InMemory::plainText($inline, $passphrase);
        }
        if ($path !== null && $path !== '' && is_file($path)) {
            return InMemory::file($path, $passphrase);
        }
        throw new RuntimeException(sprintf(
            'JWT %s key is not configured (no inline value and path "%s" missing).',
            $label,
            (string) $path,
        ));
    }
}
