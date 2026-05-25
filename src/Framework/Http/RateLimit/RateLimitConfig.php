<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

/**
 * Translates the plain-PHP `config/rate-limit.php` array shape into a list of
 * {@see RateLimitRule} objects. Kept separate from {@see RateLimitMiddleware}
 * so the middleware itself has zero knowledge of the config-file format.
 *
 * Expected shape:
 *
 *   return [
 *       'enabled' => true,
 *       'trust_proxy' => false,
 *       'rules' => [
 *           ['path_prefix' => '/api/v1/auth/login', 'limit' => 5, 'window' => 60],
 *           ['path_prefix' => '/api/v1/',           'limit' => 120, 'window' => 60, 'key' => 'ip'],
 *       ],
 *   ];
 */
final class RateLimitConfig
{
    /**
     * @param array<string, mixed> $config
     * @return list<RateLimitRule>
     */
    public static function rulesFromArray(array $config): array
    {
        $raw = $config['rules'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $rules = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $prefix = isset($entry['path_prefix']) ? (string) $entry['path_prefix'] : null;
            $limit = isset($entry['limit']) ? (int) $entry['limit'] : null;
            $window = isset($entry['window']) ? (int) $entry['window'] : null;
            if ($prefix === null || $limit === null || $window === null) {
                continue;
            }
            if ($limit < 1 || $window < 1) {
                continue;
            }
            $rules[] = new RateLimitRule(
                pathPrefix: $prefix,
                limit: $limit,
                window: $window,
                keyStrategy: isset($entry['key']) ? (string) $entry['key'] : RateLimitRule::KEY_IP,
                name: isset($entry['name']) ? (string) $entry['name'] : null,
            );
        }
        return $rules;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function isEnabled(array $config): bool
    {
        return isset($config['enabled']) ? (bool) $config['enabled'] : false;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function trustProxy(array $config): bool
    {
        return isset($config['trust_proxy']) ? (bool) $config['trust_proxy'] : false;
    }
}
