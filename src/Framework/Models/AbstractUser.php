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
 *
 * @property $remember_token not persist
 * @property $expiry_timestamp not persist
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
        $sql = 'SELECT * FROM user WHERE id = :id';
        $db = static::connection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());

        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * @return array|false
     */
    public static function findAll()
    {
        $sql = 'SELECT * FROM user';
        $db = static::connection();
        $stmt = $db->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find a user model by UUID
     * @param string $uuid The user UUID
     * @return AbstractUser|false Signup object if found, false otherwise
     */
    public static function findByUUID(string $uuid): AbstractUser | false
    {
        $sql = 'SELECT * FROM user WHERE uuid = :uuid';
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

    /**
     * Authenticate a user by email and password.
     *
     * @param string $email email address
     * @param string $password password
     *
     * @return User|false The user object or false if authentication fails
     */
    public static function authenticate(string $email, string $password) : User | false
    {
        $user = static::findByEmail($email);

        if ($user && $user->is_active) {
            if (password_verify($password, $user->password_hash)) {
                $user->cleanSecurityFields();
                return $user;
            }
        }

        return false;
    }

    /**
     * Remove all the security fields from memory objects
     */
    private function cleanSecurityFields(): void
    {
        unset($this->password);
        unset($this->password_hash);
        unset($this->password_confirm);
        unset($this->activation_token);
        unset($this->remember_token);
        unset($this->password_reset_hash);
        unset($this->password_reset_expires_at);
        unset($this->activation_hash);
        unset($this->expiry_timestamp);
    }

    /**
     * Remember the login y inserting a new unique token into remembered_logins table
     * for this user record
     *
     * @return boolean True if the login was remembered successfully, false otherwise
     * @throws Exception
     */
    public function rememberLogin(): bool
    {
        $token = new Token();
        $hashed_token = $token->getHash();
        $this->remember_token = $token->getValue();

        $this->expiry_timestamp = time() + 60 * 60 * 24 * 30; //30 days from now

        $sql = 'INSERT INTO remembered_logins (token_hash, user_id, expires_at)
                VALUES (:token_hash, :user_id, :expires_at)';
        $db = static::connection();
        $stmt = $db->prepare($sql);

        $stmt->bindValue(':token_hash', $hashed_token, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':expires_at', date('Y-m-d H:i:s', $this->expiry_timestamp), PDO::PARAM_STR);

        return $stmt->execute();

    }

    public function hasRole($role) : bool
    {
        $sql = 'SELECT COUNT(*) FROM user_role 
                LEFT JOIN role ON user_role.role_id = role.id
                WHERE user_id = :user_id AND role.name = :role';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':user_id', $this->id, PDO::PARAM_INT);
        $statement->bindParam(':role', $role, PDO::PARAM_STR);
        $statement->execute();
        return $statement->fetchColumn() > 0;
    }

    public function addRole(string $role) : bool
    {
        $sql = 'INSERT INTO user_role (user_id, role_id)
                VALUES (:user_id, (SELECT id FROM role WHERE name = :role))';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':user_id', $this->id, PDO::PARAM_INT);
        $statement->bindParam(':role', $role, PDO::PARAM_STR);
        return $statement->execute();
    }

    public function removeRole(string $role) : bool
    {
        $sql = 'DELETE FROM user_role
                WHERE user_id = :user_id AND role_id = (SELECT id FROM role WHERE name = :role)';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':user_id', $this->id, PDO::PARAM_INT);
        $statement->bindParam(':role', $role, PDO::PARAM_STR);
        return $statement->execute();
    }

    public function getRoles() : array
    {
        $sql = 'SELECT role.name FROM user_role 
                LEFT JOIN role ON user_role.role_id = role.id
                WHERE user_id = :user_id';
        $connection = self::connection();
        $statement = $connection->prepare($sql);
        $statement->bindParam(':user_id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }
}