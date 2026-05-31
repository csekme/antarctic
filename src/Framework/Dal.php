<?php
namespace Framework;
use PDO;
use Random\RandomException;

#[\AllowDynamicProperties]
abstract class Dal {
    const TRUE = 1;
    const FALSE = 0;
    /**
     * Error messages
     * @var array error messages
     */
    protected array $errors = [];

    protected mixed $data = [];

    public function jsonSerialize(): string
    {
        return json_encode($this);
    }

    /**
     * Get associative array from JSON
     * @param $json
     * @return mixed
     */
    public static function jsonToAssociative($json): mixed
    {
        return json_decode($json, true);
    }

    /**
     * @throws RandomException
     */
    public function __construct($data = [])
    {
        $this->data = $data;

        if (!isset($this->uuid)) {
            $this->uuid = $this->UUID();
        }
        foreach ($data as $key => $value) {
            $this->$key = $value;
        };
    }

    /**
     * Add an error message
     * @param string $name the name of the field
     * @param string $text the error message
     * @return void
     */
    public function addError(string $name, string $text): void
    {
        $this->errors[] = ['name' => $name, 'text' => $text];
    }

    /**
     * Generate RFC 4122 compliant Version 4 UUID
     * @return string
     * @throws RandomException
     */
    protected function UUID() : string {
        return Common::generateUUID();
    }

    /**
     * Return the database host
     * @return string
     */
    private static function getDbHost() : string
    {
        // NB. MariaDB is served via the pdo_mysql driver (mysql: DSN); there
        // is no separate `mariadb:` PDO prefix until php-src ships a dedicated
        // pdo_mariadb driver. Using `mariadb:` here would produce
        // "could not find driver" at runtime.
        return match (strtoupper($_ENV["DATABASE"] ?? '')) {
            'POSTGRESQL' => 'pgsql:host=',
            'MARIADB', 'MYSQL' => 'mysql:host=',
            default => 'mysql:host=',
        };
    }

    private static ?PDO $connection = null;

    /**
     * Public accessor for the lazily-built PDO connection. Intended for
     * services (repositories, console commands) outside the Dal model
     * hierarchy that still need direct DB access. M4.a replaces this
     * with constructor injection from the DI container.
     */
    public static function getConnection(): PDO
    {
        $pdo = self::connection();
        if ($pdo === null) {
            throw new \RuntimeException('PDO connection could not be established.');
        }
        return $pdo;
    }

    /**
     * Test-only override of the cached PDO connection. Used by integration
     * tests to inject an sqlite memory database; in production the
     * connection is built lazily from $_ENV by connection().
     */
    public static function setConnection(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }

    /**
     * Return a connection to the database
     * @return PDO|null
     */
    protected static function connection(): ?PDO
    {
        if (self::$connection === null) {
            $connectionString =
                Dal::getDbHost() . $_ENV['DATABASE_HOST'] .
                ';port=' . $_ENV['DATABASE_PORT'] .
                ';dbname=' . $_ENV['DATABASE_NAME'] ;


            self::$connection = new PDO($connectionString, $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']);
            // Throw an Exception when an error occurs
            self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return self::$connection;
    }

    public function getErrorsAsJson(): false|string
    {
        return json_encode($this->getErrors(), JSON_UNESCAPED_UNICODE);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

}