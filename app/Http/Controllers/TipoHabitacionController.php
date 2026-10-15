<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTipoHabitacionRequest;
use App\Models\TipoHabitacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoHabitacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TipoHabitacion::query()->withCount('habitaciones')->orderBy('nombre');
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        return response()->json($query->paginate($request->integer('por_pagina', 15)));
    }

    public function store(SaveTipoHabitacionRequest $request): JsonResponse
    {
        $tipo = TipoHabitacion::create($request->validated());

        return response()->json(['message' => 'Tipo de habitación creado.', 'data' => $tipo], 201);
    }

    public function show(TipoHabitacion $tipo): JsonResponse
    {
        return response()->json(['data' => $tipo->loadCount('habitaciones')]);
    }

    public function update(SaveTipoHabitacionRequest $request, TipoHabitacion $tipo): JsonResponse
    {
        $tipo->update($request->validated());

        return response()->json(['message' => 'Tipo de habitación actualizado. Las reservas existentes conservan su precio.', 'data' => $tipo->fresh()]);
    }

    public function destroy(TipoHabitacion $tipo): JsonResponse
    {
        if ($tipo->habitaciones()->exists()) {
            return response()->json(['message' => 'No se puede eliminar un tipo con habitaciones asignadas. Desactívelo si ya no se usará.'], 422);
        }
        $tipo->delete();

        return response()->json(['message' => 'Tipo de habitación eliminado.']);
    }
}
