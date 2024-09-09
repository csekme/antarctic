<?php
namespace Framework\Models;
use Exception;
use Framework\Dal as Dal;
use Framework\Token;
use PDO;

/**
 * Default Signup Model
 * @property $id
 * @property $uuid
 * @property $username
 * @property $firstname
 * @property $lastname
 * @property $email
 * @property $password not persist
 * @property $password_confirm not persist
 * @property $password_hash
 * @property $activation_hash
 * @property $activation_token not persist
 * @property $is_active
 * @property $password_reset_hash
 * @property $password_reset_expires_at
 * @property $created
 */
abstract class AbstractUser extends Dal
{
    public function validate() : bool
    {
        return false;
    }

    /**
     * Save the user model to the database
     * @throws Exception In case the application secret key is not set
     */
    public function save(): bool
    {
        if ($this->validate()) {
            $password_hash = password_hash($this->password, PASSWORD_DEFAULT);
            $token = new Token();
            $hashed_token = $token->getHash();
            $this->activation_token = $token->getValue();
            $sql = 'INSERT INTO user (uuid, username, firstname, lastname, email, password_hash, activation_hash)
                    VALUES (:uuid, :username, :firstname, :lastname, :email, :password_hash, :activation_hash)';
            $connection = self::connection();
            $statement = $connection->prepare($sql);
            $statement->bindValue(':uuid', $this->uuid);
            $statement->bindValue(':username', $this->username);
            $statement->bindValue(':firstname', $this->firstname);
            $statement->bindValue(':lastname', $this->lastname);
            $statement->bindValue(':email', $this->email);
            $statement->bindValue(':password_hash', $password_hash);
            $statement->bindValue(':activation_hash', $hashed_token);
            return $statement->execute();
        }
        return false;
    }


    /**
     * Find a user model by email address
     * @param string $email email address to search for
     * @return AbstractUser|false Signup object if found, false otherwise
 */
    public static function findByEmail(string $email): AbstractUser|false
    {
        $sql = 'SELECT * FROM user WHERE email = :email';
        $db = static::connection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);

        //Az adatbázis record egy entity osztályként jön létre
        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());

        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Find a user model by username
     * @param string $username The username
     * @return AbstractUser|false Signup object if found, false otherwise
     */
    public static function findByUsername(string $username) : AbstractUser|false
    {
        $sql = 'SELECT * FROM user WHERE username = :username';
        $db = static::connection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);

        //Az adatbázis record egy entity osztályként jön létre
        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());

        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Find a user model by ID
     * @param int $id The user ID
     * @return AbstractUser|false Signup object if found, false otherwise
     */
    public static function findByID(int $id): AbstractUser | false
    {
        $sql = 'SELECT * FROM users WHERE id = :id';
        $db = static::connection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());

        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Find a user model by UUID
     * @param string $uuid The user UUID
     * @return AbstractUser|false Signup object if found, false otherwise
     */
    public static function findByUUID(string $uuid): AbstractUser | false
    {
        $sql = 'SELECT * FROM users WHERE uuid = :uuid';
        $db = static::connection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);

        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());

        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * @param string $activation_hash
     * @return bool
     */
    public static function activateByActivationHash(string $activation_hash): bool
    {
        $sql = 'UPDATE user SET is_active = 1 , activation_hash = NULL
             WHERE activation_hash = :activation_hash';
        $db = static::connection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':activation_hash', $activation_hash, PDO::PARAM_STR);
        return $stmt->execute();
    }

}