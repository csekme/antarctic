<?php

namespace Framework;

use Framework\Models\User;
use PDO;

/**
 * Remembered login model
 * @property $user_id
 * @property $token_hash
 * @property $expires_at
 * @property $options
 */
class RememberedLogin extends Dal
{

    /**
     * Find a remembered login model by the token
     *
     * @param string $token The remembered login token
     *
     * @return RememberedLogin|bool Remembered login object if found, false otherwise
     * @throws \Exception
     */
    public static function findByToken(string $token) : RememberedLogin | bool
    {
        $token = new Token($token);
        $token_hash = $token->getHash();

        $sql = 'SELECT * FROM remembered_logins
                WHERE token_hash = :token_hash';
        $db = static::connection();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':token_hash', $token_hash, PDO::PARAM_STR);

        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());
        $stmt->execute();

        return $stmt->fetch();

    }

    /**
     * Get the user model associated with this remembered login
     *
     * @return User The user model
     */
    public function getUser(): User
    {
        $user = User::findByID($this->user_id);
        $user->options = $this->options;
        return $user;
    }

    /**
     * See if the remember token has expired or not, based on the current system time
     *
     * @return boolean True if the token has expired, false otherwise
     */
    public function hasExpired(): bool
    {
        return strtotime($this->expires_at) < time();
    }

    /**
     * Delete this model
     * @return void
     */
    public function delete(): void
    {
        $sql = 'DELETE FROM remembered_logins
                WHERE token_hash = :token_hash';
        $db = static::connection();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':token_hash', $this->token_hash, PDO::PARAM_STR);

        $stmt->execute();
    }

}

