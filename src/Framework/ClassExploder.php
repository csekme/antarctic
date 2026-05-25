<?php

declare(strict_types=1);

namespace Framework;

use Composer\Autoload\ClassLoader;
use Framework\Path as PathAttribute;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Controller-osztályokat gyűjt össze, amelyek a `#[Path]` attribútumot
 * viselik. A korábbi regex-alapú scan helyett a Composer autoloader-jét
 * + `ReflectionClass`-t használ — robusztusabb minden PHP edge case-re
 * (kommentár-blokkok, többsoros class-deklarációk, csoportos attribútumok).
 *
 * Két jelölt-forrásból olvas (sorrend):
 *   1. Composer classmap — production-ban `composer dump-autoload -o` tölti.
 *   2. PSR-4 prefix → könyvtár-scan — dev-módban a classmap (általában)
 *      üres, ezért a controller namespace-ekhez tartozó mappákat rekurzívan
 *      bejárjuk a Composer által regisztrált base-dir-en keresztül.
 */
final class ClassExploder
{
    /**
     * @var array<string, array{className: string, nameSpace: string}>
     */
    private array $map = [];

    /**
     * @param list<string> $controllerNamespaces Teljes-minősített NS prefixek (`Application\Controllers`).
     */
    public function __construct(?array $controllerNamespaces = null)
    {
        $namespaces = $controllerNamespaces ?? self::defaultNamespaces();
        $loader = self::composerLoader();
        if ($loader === null) {
            return;
        }

        foreach ($namespaces as $prefix) {
            foreach (self::discoverClasses($prefix, $loader) as $fqcn) {
                $this->ingest($fqcn);
            }
        }
    }

    /**
     * @return array<string, array{className: string, nameSpace: string}>
     */
    public function get_controller_mapping(): array
    {
        return $this->map;
    }

    private function ingest(string $fqcn): void
    {
        try {
            $reflection = new ReflectionClass($fqcn);
        } catch (ReflectionException) {
            return;
        }
        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
            return;
        }

        $classAttributes = $reflection->getAttributes(PathAttribute::class);
        $hasMethodLevelPath = false;
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(PathAttribute::class) !== []) {
                $hasMethodLevelPath = true;
                break;
            }
        }

        if ($classAttributes === [] && !$hasMethodLevelPath) {
            return;
        }

        $route = '/';
        if ($classAttributes !== []) {
            try {
                /** @var PathAttribute $path */
                $path = $classAttributes[0]->newInstance();
            } catch (Throwable) {
                return;
            }
            $route = (string) ($path->path ?? '');
            if ($route === '') {
                $route = '/';
            } elseif (!str_starts_with($route, '/')) {
                $route = '/' . $route;
            }
        }

        $shortName = $reflection->getShortName();
        $namespace = $reflection->getNamespaceName();
        $key = $route === '/' ? '/__class__/' . $namespace . '\\' . $shortName : $route;
        $this->map[$key] = [
            'className' => $shortName,
            'nameSpace' => $namespace,
        ];
    }

    /**
     * @return list<string>
     */
    private static function defaultNamespaces(): array
    {
        $namespaces = ['Application\\Controllers'];
        if (Config::useCoreController()) {
            $namespaces[] = 'Framework\\Controllers';
        }
        return $namespaces;
    }

    /**
     * @return iterable<string> az adott NS prefix alá tartozó FQCN-ek.
     */
    private static function discoverClasses(string $prefix, ClassLoader $loader): iterable
    {
        $needle = rtrim($prefix, '\\') . '\\';
        $found = [];

        // 1) Classmap (production-optimized autoloader)
        foreach ($loader->getClassMap() as $class => $_file) {
            if (str_starts_with((string) $class, $needle)) {
                $found[(string) $class] = true;
            }
        }

        // 2) PSR-4 filesystem fallback (dev)
        foreach ($loader->getPrefixesPsr4() as $psrPrefix => $dirs) {
            $psrNeedle = rtrim((string) $psrPrefix, '\\') . '\\';
            // A PSR-4 prefix vagy le-ágazata, vagy felső szintje a controller ns-nek.
            $prefixIsParent = str_starts_with($needle, $psrNeedle);
            $prefixIsChild = str_starts_with($psrNeedle, $needle);
            if (!$prefixIsParent && !$prefixIsChild) {
                continue;
            }
            foreach ($dirs as $baseDir) {
                $baseDir = (string) $baseDir;
                if ($prefixIsParent) {
                    // pl. PSR-4 'Application\\' → 'src/Application/', needle 'Application\\Controllers\\'
                    $relativeNs = substr($needle, strlen($psrNeedle));
                    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeNs);
                    $scanRoot = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($relativePath, DIRECTORY_SEPARATOR);
                } else {
                    // pl. PSR-4 'Application\\Controllers\\Api\\' → bázis-dir már a needle alá esik
                    $scanRoot = $baseDir;
                }
                if (!is_dir($scanRoot)) {
                    continue;
                }
                foreach (self::scanDirectory($scanRoot) as $relFile) {
                    $relativeNs = str_replace(DIRECTORY_SEPARATOR, '\\', substr($relFile, 0, -4));
                    $fqcn = $prefixIsParent
                        ? $psrNeedle . substr($needle, strlen($psrNeedle)) . $relativeNs
                        : $psrNeedle . $relativeNs;
                    $fqcn = ltrim($fqcn, '\\');
                    if (str_starts_with($fqcn, $needle)) {
                        $found[$fqcn] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }

    /**
     * @return iterable<string> relative file paths under `$dir` ending in `.php`.
     */
    private static function scanDirectory(string $dir): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        $base = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $full = $file->getPathname();
            if (str_starts_with($full, $base)) {
                yield substr($full, strlen($base));
            }
        }
    }

    private static function composerLoader(): ?ClassLoader
    {
        if (!class_exists(ClassLoader::class, false) && !class_exists(ClassLoader::class)) {
            return null;
        }
        $loaders = ClassLoader::getRegisteredLoaders();
        if ($loaders === []) {
            return null;
        }
        return array_values($loaders)[0];
    }
}
