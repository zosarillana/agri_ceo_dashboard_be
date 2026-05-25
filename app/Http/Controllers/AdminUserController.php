<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    protected AdminUserService $adminUserService;

    public function __construct(AdminUserService $adminUserService)
    {
        $this->adminUserService = $adminUserService;
    }

    /**
     * GET /api/admin/users
     * Returns all users.
     */
    public function index()
    {
        $users = $this->adminUserService->getAllUsers();

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * PUT /api/admin/users/update
     * Updates any user by ID (admin only).
     * Body: { id, name?, email?, department?, password?, password_confirmation? }
     */
    public function update(Request $request)
    {
        $request->validate([
            'id'         => 'required|integer|exists:users,id',
            'name'       => 'sometimes|string',
            'email'      => 'sometimes|string|email|unique:users,email,' . $request->id,
            'department' => 'nullable|string',
            'role'       => 'sometimes|string|in:superadmin,admin,user',
            'password'   => 'nullable|string|min:6|confirmed',
        ]);

        $user = User::findOrFail($request->id);

        $updatedUser = $this->adminUserService->updateUser($user, $request->only([
            'name', 'email', 'department', 'role', 'password',
        ]));

        return response()->json([
            'message' => 'User updated successfully.',
            'user'    => $updatedUser,
        ]);
    }

    /**
     * DELETE /api/admin/users/delete
     * Deletes any user by ID (admin only).
     * Body: { id }
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:users,id',
        ]);

        $this->adminUserService->deleteUser($request->id);

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }
}