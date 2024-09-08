<?php
namespace Framework\Models;
class User extends AbstractUser {


    #[\Override]
    public function validate() : bool
    {
         if (empty($this->username)) {
             $this->errors[] = ['name' => 'username', 'text'=>'Username is required'];
         }
         if (empty($this->email)) {
             $this->errors[] = ['name' => 'email', 'text'=>'Email is required'];
         }
         if (empty($this->password)) {
             $this->errors[] = ['name' => 'password', 'text'=>'Password is required'];
         }
         if ($this->password_confirm != $this->password) {
             $this->errors[] = ['name' => 'password_confirm', 'text'=>'Passwords do not match'];
         }

         if (User::findByUsername($this->username)) {
             $this->errors[] = ['name' => 'username', 'text'=>'Username already exists'];
             return false;
         }

         if (User::findByEmail($this->email) === false) {
             $this->errors[] = ['name' => 'email', 'text'=>'This email is already registered'];
             return false;
         }


         return empty($this->errors);
    }


}