<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Reserva;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $activas = Habitacion::where('activo', true);
        $total = (clone $activas)->count();
        $ocupadas = (clone $activas)->where('estado', 'ocupada')->count();
        $mes = [today()->startOfMonth(), today()->endOfMonth()->endOfDay()];

        return response()->json(['data' => [
            'habitaciones_total' => $total,
            'habitaciones_disponibles' => (clone $activas)->where('estado', 'disponible')->count(),
            'habitaciones_ocupadas' => $ocupadas,
            'habitaciones_limpieza' => (clone $activas)->where('estado', 'limpieza')->count(),
            'habitaciones_mantenimiento' => (clone $activas)->where('estado', 'mantenimiento')->count(),
            'ocupacion_porcentaje' => $total ? round($ocupadas * 100 / $total, 1) : 0,
            'llegadas_hoy' => Reserva::where('estado', 'reservada')->whereDate('fecha_entrada', today())->count(),
            'salidas_hoy' => Reserva::where('estado', 'en_estadia')->whereDate('fecha_salida', today())->count(),
            'huespedes_en_estadia' => Reserva::where('estado', 'en_estadia')->count(),
            'reservas_pendientes' => Reserva::where('estado', 'reservada')->count(),
            'ingresos_mes' => (float) Reserva::where('estado', 'finalizada')->whereBetween('check_out_en', $mes)->sum('total'),
        ]]);
    }
}
