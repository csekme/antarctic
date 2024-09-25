<?php

namespace Framework\Models;

class User extends AbstractUser
{
    #[\Override]
    public function validate(bool $forUpdate = false): bool
    {
        if (empty($this->username)) {
            $this->errors[] = ['name' => 'username', 'text' => 'Username is required'];
        }

        if (empty($this->email)) {
            $this->errors[] = ['name' => 'email', 'text' => 'Email is required'];
        }

        if (User::findByUsername($this->username)) {
            $this->errors[] = ['name' => 'username', 'text' => 'Username already exists'];
            return false;
        }

        if (User::findByEmail($this->email)) {
            $this->errors[] = ['name' => 'email', 'text' => 'This email is already registered'];
            return false;
        }

        return empty($this->errors) && $this->validateProfileData() && $this->validateSecurityFields();
    }

    #[\Override]
    public function validatePasswordChanging() : bool
    {
        if (!empty($this->old_password)) {
            $tmpUsr = User::findByUUID($this->uuid);
            if (!password_verify($this->old_password, $tmpUsr->password_hash)) {
                $this->errors[] = ['name' => 'old_password', 'text' => 'Your old password is wrong'];
            } else if ($this->password_confirm != $this->password) {
                $this->errors[] = ['name' => 'password_confirm', 'text' => 'Passwords do not match'];
            }
        }
        return empty($this->errors);
    }

    public function validateSecurityFields(): bool
    {
        if (empty($this->password)) {
            $this->errors[] = ['name' => 'password', 'text' => 'Password is required'];
        }
        if ($this->password_confirm != $this->password) {
            $this->errors[] = ['name' => 'password_confirm', 'text' => 'Passwords do not match'];
        }

        return empty($this->errors);
    }

    #[\Override]
    public function validateProfileData() : bool {
        return empty($this->errors);
    }


}