<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveHabitacionRequest;
use App\Models\Habitacion;
use App\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HabitacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Habitacion::query()->with('tipo')->orderBy('piso')->orderBy('numero');
        if ($estado = $request->string('estado')->trim()->toString()) {
            $query->where('estado', $estado);
        }
        if ($tipo = $request->integer('tipo_habitacion_id')) {
            $query->where('tipo_habitacion_id', $tipo);
        }
        if ($request->filled('piso')) {
            $query->where('piso', $request->integer('piso'));
        }
        if ($search = $request->string('buscar')->trim()->toString()) {
            $query->where('numero', 'like', "%{$search}%");
        }

        return response()->json($query->paginate($request->integer('por_pagina', 15)));
    }

    /**
     * Disponibilidad por rango de fechas: habitaciones activas sin reservas que se crucen.
     * Si la entrada es hoy o anterior, además exige que la habitación esté físicamente disponible.
     */
    public function disponibles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha_entrada' => ['required', 'date'],
            'fecha_salida' => ['required', 'date', 'after:fecha_entrada'],
            'tipo_habitacion_id' => ['nullable', 'integer', 'exists:tipos_habitacion,id'],
            'personas' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Habitacion::query()->with('tipo')->where('activo', true)
            ->whereDoesntHave('reservas', fn ($q) => $q->cruzaCon($data['fecha_entrada'], $data['fecha_salida']))
            ->whereHas('tipo', fn ($q) => $q->where('activo', true)
                ->when($data['personas'] ?? null, fn ($q2, $p) => $q2->where('capacidad', '>=', $p)))
            ->when($data['tipo_habitacion_id'] ?? null, fn ($q, $id) => $q->where('tipo_habitacion_id', $id))
            ->orderBy('piso')->orderBy('numero');

        if ($data['fecha_entrada'] <= today()->toDateString()) {
            $query->where('estado', 'disponible');
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(SaveHabitacionRequest $request): JsonResponse
    {
        $habitacion = Habitacion::create($request->validated());

        return response()->json(['message' => 'Habitación creada.', 'data' => $habitacion->load('tipo')], 201);
    }

    public function show(Habitacion $habitacion): JsonResponse
    {
        $habitacion->load(['tipo', 'reservas' => fn ($q) => $q->whereIn('estado', Reserva::ESTADOS_ACTIVOS)->orderBy('fecha_entrada')->with('huesped:id,nombre,apellido,documento')]);

        return response()->json(['data' => $habitacion]);
    }

    public function update(SaveHabitacionRequest $request, Habitacion $habitacion): JsonResponse
    {
        $data = $request->validated();
        $tieneActivas = $habitacion->reservas()->whereIn('estado', Reserva::ESTADOS_ACTIVOS)->exists();
        if ($tieneActivas && (($data['activo'] ?? true) === false || (isset($data['estado']) && $data['estado'] !== $habitacion->estado))) {
            return response()->json(['message' => 'La habitación tiene reservas activas; no se puede desactivar ni cambiar su estado manualmente.'], 422);
        }
        if (isset($data['estado']) && $data['estado'] === 'ocupada' && $habitacion->estado !== 'ocupada') {
            return response()->json(['message' => 'El estado ocupada solo se asigna al registrar el check-in de una reserva.'], 422);
        }
        $habitacion->update($data);

        return response()->json(['message' => 'Habitación actualizada.', 'data' => $habitacion->fresh()->load('tipo')]);
    }

    /** Cambio rápido de estado (limpieza → disponible, mantenimiento, etc.). */
    public function cambiarEstado(Request $request, Habitacion $habitacion): JsonResponse
    {
        $data = $request->validate(['estado' => ['required', Rule::in(['disponible', 'limpieza', 'mantenimiento'])]]);
        if ($habitacion->reservas()->where('estado', 'en_estadia')->exists()) {
            return response()->json(['message' => 'La habitación está ocupada por un huésped; el estado cambia con el check-out.'], 422);
        }
        $habitacion->update(['estado' => $data['estado']]);

        return response()->json(['message' => 'Estado de la habitación actualizado.', 'data' => $habitacion->fresh()->load('tipo')]);
    }

    public function destroy(Habitacion $habitacion): JsonResponse
    {
        if ($habitacion->reservas()->exists()) {
            return response()->json(['message' => 'No se puede eliminar una habitación con reservas registradas. Desactívela.'], 422);
        }
        $habitacion->delete();

        return response()->json(['message' => 'Habitación eliminada.']);
    }
}
