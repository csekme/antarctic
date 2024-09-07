<?php
namespace Framework\Models;
use Exception;
use Framework\Dal as Dal;
use Framework\Token;

/**
 * Default User Model
 * @property $id
 * @property $uuid
 * @property $username
 * @property $firstname
 * @property $lastname
 * @property $email
 * @property $password not persist
 * @property $password_retry not persist
 * @property $password_hash
 * @property $activation_hash
 * @property $is_active
 * @property $password_reset_hash
 * @property $password_reset_expires_at
 * @property $created
 */
abstract class AbstractUser extends Dal
{
    public function validate()
    {

    }

    /**
     * Save the user model to the database
     * @throws Exception In case the application secret key is not set
     */
    public function save(): bool
    {
        $this->validate();
        if (empty($this->errors)) {
            $password_hash = password_hash($this->password, PASSWORD_DEFAULT);
            $token = new Token();
            $hashed_token = $token->getHash();
            $this->activation_hash = $token->getValue();
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
}