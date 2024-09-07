<?php
namespace Framework;
use PDO;
use Random\RandomException;

#[\AllowDynamicProperties]
abstract class Dal {

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
     * @return string|void
     * @throws RandomException
     */
    protected function UUID() {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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

    public function getErrors() {
        return $this->errors;
    }


}