<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $credentials['email'] = strtolower($credentials['email']);

        $user = User::where('email', $credentials['email'])->first();
        if ($user && Hash::check($credentials['password'], $user->password)) {
            // Check global login setting
            $loginSetting = \App\Models\Setting::where('key', 'login_enabled')->value('value') ?? 'true';
            if ($loginSetting === 'false' && $user->role !== 'admin') {
                return response()->json(['message' => 'Error 404 please ask to the ADMIN'], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json(['token' => $token, 'user' => $user], 200);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function register(Request $request)
    {
        // Check global register setting
        $registerSetting = \App\Models\Setting::where('key', 'register_enabled')->value('value') ?? 'true';
        if ($registerSetting === 'false') {
            return response()->json(['message' => 'Error 404 please ask to the ADMIN'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => 'user',
        ]);

        return response()->json(['message' => 'Registered successfully'], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string',
            'password' => 'nullable|string|min:6',
        ]);

        $user->name = $validated['name'];
        if (isset($validated['image'])) {
            $user->image = $validated['image'];
        }
        if (!empty($validated['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }

        $user->save();

        return response()->json($user, 200);
    }
}
