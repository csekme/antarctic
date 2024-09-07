<?php
namespace Framework\Models;
class User extends AbstractUser {
    public function validate()
    {
         if (empty($this->username)) {
             $this->errors[] = ['name' => 'username', 'text'=>'Username is required'];
         }
    }


}