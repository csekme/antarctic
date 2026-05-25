<?php

declare(strict_types=1);

namespace Framework\Console;

use RuntimeException;

/**
 * RSA 4096 kulcspárt generál a JWT aláíráshoz.
 *
 *   bin/console keys:generate              # var/keys/jwt-{private,public}.pem
 *   bin/console keys:generate --force      # felülírja a meglévő kulcsokat
 *   bin/console keys:generate --bits=2048  # alapértelmezett 4096 helyett
 *   bin/console keys:generate --out=/abs/path/prefix   # /abs/path/prefix-private.pem + …-public.pem
 */
final class KeysGenerateCommand implements Command
{
    public function __construct(private readonly string $defaultOutDir)
    {
    }

    public function name(): string
    {
        return 'keys:generate';
    }

    public function description(): string
    {
        return 'Generates an RSA keypair for JWT signing (RS256).';
    }

    public function run(array $argv): int
    {
        if (!extension_loaded('openssl')) {
            fwrite(STDERR, "ext-openssl is required.\n");
            return 1;
        }

        $bits = 4096;
        $force = false;
        $outPrefix = $this->defaultOutDir . '/jwt';

        foreach ($argv as $arg) {
            if ($arg === '--force') {
                $force = true;
            } elseif (str_starts_with($arg, '--bits=')) {
                $bits = (int) substr($arg, 7);
            } elseif (str_starts_with($arg, '--out=')) {
                $outPrefix = substr($arg, 6);
            } else {
                fwrite(STDERR, "Unknown argument: {$arg}\n");
                return 1;
            }
        }

        if ($bits < 2048) {
            fwrite(STDERR, "Refusing to generate a key smaller than 2048 bits.\n");
            return 1;
        }

        $privatePath = $outPrefix . '-private.pem';
        $publicPath = $outPrefix . '-public.pem';

        if (!$force && (file_exists($privatePath) || file_exists($publicPath))) {
            fwrite(STDERR, "Refusing to overwrite existing keys. Pass --force to override.\n");
            fwrite(STDERR, "  {$privatePath}\n  {$publicPath}\n");
            return 1;
        }

        $dir = dirname($privatePath);
        if (!is_dir($dir) && !mkdir($dir, 0o700, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }

        echo "Generating RSA-{$bits} keypair…\n";
        $resource = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            fwrite(STDERR, "openssl_pkey_new failed: " . openssl_error_string() . "\n");
            return 1;
        }

        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);
        if ($details === false || !isset($details['key'])) {
            fwrite(STDERR, "openssl_pkey_get_details failed.\n");
            return 1;
        }
        $publicPem = $details['key'];

        if (file_put_contents($privatePath, $privatePem) === false
            || file_put_contents($publicPath, $publicPem) === false
        ) {
            fwrite(STDERR, "Failed to write keys.\n");
            return 1;
        }

        chmod($privatePath, 0o600);
        chmod($publicPath, 0o644);

        echo "  private: {$privatePath} (mode 0600)\n";
        echo "  public : {$publicPath} (mode 0644)\n";
        echo "\nDone. Add these to .env for production:\n";
        echo "  JWT_PRIVATE_KEY_PATH={$privatePath}\n";
        echo "  JWT_PUBLIC_KEY_PATH={$publicPath}\n";

        return 0;
    }
}
