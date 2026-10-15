<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveHuespedRequest;
use App\Models\Huesped;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HuespedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Huesped::query()->withCount('reservas')->orderBy('apellido')->orderBy('nombre');
        if ($search = $request->string('buscar')->trim()->toString()) {
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$search}%")->orWhere('apellido', 'like', "%{$search}%")->orWhere('documento', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate($request->integer('por_pagina', 15)));
    }

    public function store(SaveHuespedRequest $request): JsonResponse
    {
        $huesped = Huesped::create($request->validated());

        return response()->json(['message' => 'Huésped registrado.', 'data' => $huesped], 201);
    }

    public function show(Huesped $huesped): JsonResponse
    {
        return response()->json(['data' => $huesped->load(['reservas' => fn ($q) => $q->latest('fecha_entrada')->with('habitacion:id,numero')])]);
    }

    public function update(SaveHuespedRequest $request, Huesped $huesped): JsonResponse
    {
        $huesped->update($request->validated());

        return response()->json(['message' => 'Huésped actualizado.', 'data' => $huesped->fresh()]);
    }

    public function destroy(Huesped $huesped): JsonResponse
    {
        if ($huesped->reservas()->exists()) {
            return response()->json(['message' => 'No se puede eliminar un huésped con reservas registradas. Desactívelo.'], 422);
        }
        $huesped->delete();

        return response()->json(['message' => 'Huésped eliminado.']);
    }
}
