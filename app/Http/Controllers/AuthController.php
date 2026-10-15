<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:200'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::with('role')->where('username', $data['username'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }
        if ($user->status !== 'active') {
            return response()->json(['message' => 'El usuario no está activo.'], 403);
        }

        $expiresAt = now()->addHour();
        $token = $user->createToken($data['device_name'], ['profile:read', 'hotel:manage'], $expiresAt);

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $user->perfil(),
        ])->header('Cache-Control', 'no-store');
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load('role')->perfil()])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($request->user())],
            'gender' => ['required', 'string', 'max:20'],
            'birth' => ['required', 'date'],
        ]);
        $request->user()->update($data);

        return response()->json(['message' => 'Perfil actualizado.', 'user' => $request->user()->fresh()->load('role')->perfil()]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }
        $request->user()->update(['password' => $data['password']]);
        $request->user()->tokens()->whereKeyNot($request->user()->currentAccessToken()?->id)->delete();

        return response()->json(['message' => 'Contraseña actualizada.']);
    }
}
