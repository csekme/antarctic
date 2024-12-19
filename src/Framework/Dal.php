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
        return match (strtoupper($_ENV["DATABASE"])) {
            'MARIADB' => 'mariadb:host=',
            'POSTGRESQL' => 'pgsql:host=',
            'MYSQL' => 'mysql:host=',
            default => 'mysql:host=',
        };
    }

    /**
     * Return a connection to the database
     * @return PDO|null
     */
    protected static function connection(): ?PDO
    {
        static $db = null;
        if ($db === null) {
            $connectionString =
                Dal::getDbHost() . $_ENV['DATABASE_HOST'] .
                ';port=' . $_ENV['DATABASE_PORT'] .
                ';dbname=' . $_ENV['DATABASE_NAME'] ;


            $db = new PDO($connectionString, $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']);
            // Throw an Exception when an error occurs
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return $db;
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