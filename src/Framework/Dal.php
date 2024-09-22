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


    /**
     * @throws RandomException
     */
    public function __construct($data = [])
    {
        if (!isset($this->uuid)) {
            $this->uuid = $this->UUID();
        }
        foreach ($data as $key => $value) {
            $this->$key = $value;
        };
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
            'POSTGRESQL' => 'psql:host=',
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

    public function getErrors(): false|string
    {
        return json_encode($this->errors, JSON_UNESCAPED_UNICODE);
    }


}