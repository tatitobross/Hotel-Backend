<?php

namespace App\Services;

use App\Models\Habitacion;
use App\Models\Reserva;
use Carbon\Carbon;

class ReservaService
{
    /** Noches entre dos fechas (mínimo 1; la fecha de salida debe ser posterior a la de entrada). */
    public function noches(string $entrada, string $salida): int
    {
        return max(1, (int) Carbon::parse($entrada)->startOfDay()->diffInDays(Carbon::parse($salida)->startOfDay()));
    }

    /** @return array{noches:int, precio_noche:float, costo_hospedaje:float} */
    public function calcular(Habitacion $habitacion, string $entrada, string $salida): array
    {
        $noches = $this->noches($entrada, $salida);
        $precio = (float) $habitacion->tipo->precio_noche;

        return ['noches' => $noches, 'precio_noche' => $precio, 'costo_hospedaje' => round($noches * $precio, 2)];
    }

    /** Lanza 422 si la habitación ya tiene una reserva activa que se cruza con el rango. */
    public function assertSinCruce(int $habitacionId, string $entrada, string $salida, ?int $ignorarReservaId = null): void
    {
        $cruce = Reserva::query()
            ->where('habitacion_id', $habitacionId)
            ->cruzaCon($entrada, $salida)
            ->when($ignorarReservaId, fn ($q) => $q->whereKeyNot($ignorarReservaId))
            ->first(['id', 'fecha_entrada', 'fecha_salida']);

        if ($cruce) {
            abort(422, sprintf(
                'La habitación ya está reservada del %s al %s (reserva #%d). Elija otras fechas u otra habitación.',
                $cruce->fecha_entrada->toDateString(), $cruce->fecha_salida->toDateString(), $cruce->id
            ));
        }
    }

    public function recalcularTotales(Reserva $reserva): Reserva
    {
        $consumos = (float) $reserva->consumos()->sum('total');
        $reserva->update([
            'total_consumos' => $consumos,
            'total' => round((float) $reserva->costo_hospedaje + $consumos, 2),
        ]);

        return $reserva;
    }
}
