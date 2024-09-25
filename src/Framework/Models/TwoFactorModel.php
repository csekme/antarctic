<?php

namespace Framework\Models;
use Framework\Dal;
use PDO;

/**
 * Class TwoFactorModel
 * @package Framework\Models
 * @since 1.0
 * @license GPL-3.0-or-later
 * @author Krisztián Csekme
 * @category Framework
 * @version 1.0
 * @Path(path: '/Framework/Models/TwoFactorModel.php')
 * @Table (name="two_factor")
 * @property int $id
 * @property int $user_id
 * @property string $method
 * @property string $secret_key
 * @property string $passcode
 * @property int $enabled
 * @property string $passcode_expired_at
 */
class TwoFactorModel extends Dal
{

    public const METHOD_EMAIL = 'email';
    public const METHOD_APP = 'app';

    /**
     * Find TwoFactorModel by user_id and method
     * @param int $userId User ID
     * @param string $method TwoFactorModel::METHOD_EMAIL|TwoFactorModel::METHOD_APP
     * @return TwoFactorModel|false TwoFactorModel or false
     */
    public static function findByUserId(int $userId): array | false
    {
        $sql = 'SELECT * FROM two_factor WHERE user_id = :user_id';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $statement->setFetchMode(PDO::FETCH_CLASS, get_called_class());
        $statement->execute();
        return $statement->fetchAll();
    }

    /**
     * Find TwoFactorModel by user_id and method
     * @param int $userId User ID
     * @param string $method TwoFactorModel::METHOD_EMAIL|TwoFactorModel::METHOD_APP
     * @return TwoFactorModel|false TwoFactorModel or false
     */
    public static function findByUserIdAndMethod(int $userId, string $method): TwoFactorModel | false
    {
        $sql = 'SELECT * FROM two_factor WHERE user_id = :user_id AND method = :method';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindParam(':method', $method, PDO::PARAM_STR);
        $statement->setFetchMode(PDO::FETCH_CLASS, get_called_class());
        $statement->execute();
        return $statement->fetch();
    }

    public function save() : bool
    {
        $sql = 'INSERT INTO two_factor (user_id, method, secret_key, passcode, enabled, passcode_expired_at) 
        VALUES (:user_id, :method, :secret_key, :passcode, :enabled, :passcode_expired_at)';
        $connection = static::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $statement->bindParam(':method', $this->method, PDO::PARAM_STR);
        $statement->bindParam(':secret_key', $this->secret_key, PDO::PARAM_STR);
        $statement->bindParam(':passcode', $this->passcode, PDO::PARAM_STR);
        $statement->bindParam(':enabled', $this->enabled, PDO::PARAM_INT);
        $statement->bindParam(':passcode_expired_at', $this->passcode_expired_at, PDO::PARAM_STR);
        return $statement->execute();
    }

    public function update() : bool
    {
        $sql = 'UPDATE two_factor SET secret_key = :secret_key, passcode = :passcode, enabled = :enabled, passcode_expired_at = :passcode_expired_at WHERE user_id = :user_id AND method = :method';
        $connection = static::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $statement->bindParam(':method', $this->method, PDO::PARAM_STR);
        $statement->bindParam(':secret_key', $this->secret_key, PDO::PARAM_STR);
        $statement->bindParam(':passcode', $this->passcode, PDO::PARAM_STR);
        $statement->bindParam(':enabled', $this->enabled, PDO::PARAM_INT);
        $statement->bindParam(':passcode_expired_at', $this->passcode_expired_at, PDO::PARAM_STR);
        return $statement->execute();
    }

}