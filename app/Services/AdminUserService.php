<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserService
{
    /**
     * Return all users (excluding password).
     */
    public function getAllUsers()
    {
        return User::select('id', 'name', 'email', 'role', 'created_at')
            ->with('departments:id,name')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Update any user's profile fields.
     */
    public function updateUser(User $user, array $data): User
    {
        $user->name = $data['name'] ?? $user->name;
        $user->email = $data['email'] ?? $user->email;
        $user->role = $data['role'] ?? $user->role;

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // ✅ THIS is how you update departments now
        if (isset($data['department_ids'])) {
            $user->departments()->sync($data['department_ids']);
        }

        return $user->fresh();
    }

    /**
     * Permanently delete a user by ID.
     */
    public function deleteUser(int $id): void
    {
        User::findOrFail($id)->delete();
    }
}
