<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    protected UserService $userService;

    // Inject the service via constructor
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'       => 'sometimes|string',
            'email'      => 'sometimes|string|email|unique:users,email,' . $user->id,
            'department' => 'nullable|string',
            'password'   => 'nullable|string|min:6|confirmed',
        ]);

        $updatedUser = $this->userService->updateUser($user, $request->only([
            'name', 'email', 'department', 'password'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $updatedUser,
        ]);
    }
}