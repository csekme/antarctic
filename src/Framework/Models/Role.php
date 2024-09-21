<?php

namespace Framework\Models;

use Cassandra\Uuid;
use Framework\Dal;
use PDO;

/**
 * Class Role
 * @package Framework\Models
 * @since 1.0
 * @license GPL-3.0-or-later
 * @author Krisztián Csekme
 * @category Framework
 * @version 1.0
 * @Path(path: '/Framework/Models/Role.php')
 * @Entity
 * @Table(name="role")
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $description
 */
class Role extends Dal
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';


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