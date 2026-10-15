<?php

namespace App\Http\Controllers;

use App\Models\Consumo;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConsumoController extends Controller
{
    public function __construct(private ReservaService $service) {}

    public function index(Request $request, ?Reserva $reserva = null): JsonResponse
    {
        $query = Consumo::query()->with(['reserva:id,huesped_id,habitacion_id,estado', 'registradoPor'])->latest('consumido_en');
        if ($reserva) {
            $query->where('reserva_id', $reserva->id);
        } elseif ($id = $request->integer('reserva_id')) {
            $query->where('reserva_id', $id);
        }
        if ($categoria = $request->string('categoria')->trim()->toString()) {
            $query->where('categoria', $categoria);
        }

        return response()->json($query->paginate($request->integer('por_pagina', 15)));
    }

    public function store(Request $request, Reserva $reserva): JsonResponse
    {
        $data = $request->validate([
            'categoria' => ['required', Rule::in(['restaurante', 'bar', 'lavanderia', 'minibar', 'spa', 'otro'])],
            'descripcion' => ['required', 'string', 'max:150'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            'precio_unitario' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'consumido_en' => ['nullable', 'date'],
        ]);

        $consumo = DB::transaction(function () use ($data, $reserva, $request): Consumo {
            $reserva = Reserva::query()->lockForUpdate()->findOrFail($reserva->id);
            if ($reserva->estado !== 'en_estadia') {
                abort(422, 'Solo se registran consumos de reservas con huésped en estadía (check-in hecho y sin check-out).');
            }
            $consumo = $reserva->consumos()->create([
                ...$data,
                'total' => round($data['cantidad'] * $data['precio_unitario'], 2),
                'consumido_en' => $data['consumido_en'] ?? now(),
                'registrado_por' => $request->user()->id,
            ]);
            $this->service->recalcularTotales($reserva);

            return $consumo;
        });

        return response()->json([
            'message' => 'Consumo registrado.',
            'data' => $consumo->load('registradoPor'),
            'reserva' => $consumo->reserva()->first(['id', 'costo_hospedaje', 'total_consumos', 'total']),
        ], 201);
    }

    public function destroy(Consumo $consumo): JsonResponse
    {
        DB::transaction(function () use ($consumo): void {
            $reserva = Reserva::query()->lockForUpdate()->findOrFail($consumo->reserva_id);
            if ($reserva->estado !== 'en_estadia') {
                abort(422, 'No se puede eliminar un consumo de una reserva ya cerrada.');
            }
            $consumo->delete();
            $this->service->recalcularTotales($reserva);
        });

        return response()->json(['message' => 'Consumo eliminado y totales recalculados.']);
    }
}
