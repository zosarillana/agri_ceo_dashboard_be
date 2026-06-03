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
            'id' => 'required|integer|exists:users,id',
            'name' => 'sometimes|string',
            'email' => 'sometimes|string|email|unique:users,email,'.$request->id,
            'role' => 'sometimes|string|in:superadmin,admin,user',
            'password' => 'nullable|string|min:6|confirmed',
            'department_ids' => 'nullable|array',        // ← add
            'department_ids.*' => 'exists:departments,id', // ← add
        ]);

        $user = User::findOrFail($request->id);

        $updatedUser = $this->adminUserService->updateUser($user, $request->only([
            'name', 'email', 'role', 'password', 'department_ids', // ← add department_ids, remove old 'department'
        ]));

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $updatedUser,
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
