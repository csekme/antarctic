<?php

namespace Framework;
use PDO;
abstract class Dal {


    /**
     * Return the database host
     * @return string
     */
    private static function getDbHost() : string
    {
        switch (strtoupper($_ENV["DATABASE"])) {
            case 'MARIADB':
                return 'mariadb:host=';
            case 'POSTGRESQL':
                return 'psql:host=';
            case 'MYSQL':
                return 'mysql:host=';
            default:
                return 'mysql:host=';
        }
    }

    /**
     * Return a connection to the database
     * @return \PDO|null
     */
    protected static function connection(): ?\PDO
    {
        static $db = null;
        if ($db === null) {
            $connectionString =
                Dal::getDbHost() . $_ENV['DATABASE_HOST'] .
                ';port=' . $$_ENV['DATABASE_PORT'] .
                ';dbname=' . $_ENV['DATABASE_NAME'] ;


            $db = new \PDO($connectionString, $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']);
            // Throw an Exception when an error occurs
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return $db;
    }


}