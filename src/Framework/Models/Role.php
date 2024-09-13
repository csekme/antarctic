<?php

namespace Framework\Models;

use Framework\Dal;
use PDO;

class Role extends Dal
{
    public const string ROLE_USER = 'ROLE_USER';
    public const string ROLE_ADMIN = 'ROLE_ADMIN';


    public static function findAll()
    {
        $sql = 'SELECT * FROM role';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->setFetchMode(PDO::FETCH_CLASS, get_called_class());
        $statement->execute();
        return $statement->fetchAll();
    }

    public static function findByID(int $id): Role | false
    {
        $sql = 'SELECT * FROM role WHERE id = :id';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->setFetchMode(PDO::FETCH_CLASS, get_called_class());
        $statement->execute();
        return $statement->fetch();
    }

    public static function findByName(string $name): Role | false
    {
        $sql = 'SELECT * FROM role WHERE name = :name';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':name', $name, PDO::PARAM_STR);
        $statement->setFetchMode(PDO::FETCH_CLASS, get_called_class());
        $statement->execute();
        return $statement->fetch();
    }

    public static function getRoles(AbstractUser $user) : array
    {
        $sql = 'SELECT r.* FROM role r
                JOIN user_role ur ON r.id = ur.role_id
                WHERE ur.user_id = :user_id';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':user_id', $user->id, PDO::PARAM_INT);
        $statement->setFetchMode(PDO::FETCH_CLASS, get_called_class());
        $statement->execute();
        return $statement->fetchAll();
    }

}