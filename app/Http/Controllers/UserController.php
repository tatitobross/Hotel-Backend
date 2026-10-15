<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('role')->orderBy('name');
        if ($search = $request->string('buscar')->trim()->toString()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
        }
        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }
        $page = $query->paginate($request->integer('por_pagina', 15));
        $page->getCollection()->transform(fn (User $u) => $u->perfil());

        return response()->json($page);
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = new User($data);
        $user->role_id = $data['role_id'];
        $user->status = $data['status'];
        $user->save();

        return response()->json(['message' => 'Usuario creado.', 'data' => $user->load('role')->perfil()], 201);
    }

    public function show(User $usuario): JsonResponse
    {
        return response()->json(['data' => $usuario->load('role')->perfil()]);
    }

    public function update(SaveUserRequest $request, User $usuario): JsonResponse
    {
        $data = $request->validated();
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $usuario->fill($data);
        $usuario->role_id = $data['role_id'];
        $usuario->status = $data['status'];
        $usuario->save();
        if ($usuario->status !== 'active') {
            $usuario->tokens()->delete();
        }

        return response()->json(['message' => 'Usuario actualizado.', 'data' => $usuario->fresh()->load('role')->perfil()]);
    }

    public function destroy(Request $request, User $usuario): JsonResponse
    {
        abort_if($usuario->id === $request->user()->id, 422, 'No puedes eliminar tu propia cuenta.');
        if ($usuario->reservasCreadas()->exists()) {
            return response()->json(['message' => 'El usuario tiene reservas registradas. Desactívalo en lugar de eliminarlo.'], 422);
        }
        $usuario->tokens()->delete();
        $usuario->delete();

        return response()->json(['message' => 'Usuario eliminado.']);
    }
}
