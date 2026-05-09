<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;

class UserService
{
    public function updateUser($user, array $data): object
    {
        $user->name       = $data['name']       ?? $user->name;
        $user->email      = $data['email']      ?? $user->email;
        $user->department = $data['department'] ?? $user->department;

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return $user;
    }
}