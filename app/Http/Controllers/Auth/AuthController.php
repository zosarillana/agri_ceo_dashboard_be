<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // REGISTER
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'department' => 'nullable|string|in:production,procurement,sales,accounts,trading,quality_control,workforce,maintenance,energy',
            'role' => 'nullable|string|in:superadmin,admin,user', // Add role validation
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user', // Use the role from request, default to 'user'
            'department' => $request->department,
        ]);

        // 🔥 LOG THE USER IN (SESSION)
        Auth::login($user);

        return response()->json([
            'user' => $user,
        ]);
    }

    // LOGIN
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // 🔥 IMPORTANT
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Logged in successfully',
            'user' => Auth::user(),
        ]);
    }

    // GET AUTH USER
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    // LOGOUT
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}