<?php

namespace App\Http\Controllers;

use App\Models\Consumo;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Reserva;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function csv(string $module): StreamedResponse
    {
        abort_unless(in_array($module, ['habitaciones', 'huespedes', 'reservas', 'consumos'], true), 404);

        $rows = match ($module) {
            'habitaciones' => Habitacion::query()->with('tipo:id,nombre,precio_noche')->orderBy('numero')->cursor()->map(fn ($h) => [
                'numero' => $h->numero, 'piso' => $h->piso, 'tipo' => $h->tipo->nombre, 'precio_noche' => $h->tipo->precio_noche, 'estado' => $h->estado, 'activo' => $h->activo,
            ]),
            'huespedes' => Huesped::query()->select('nombre', 'apellido', 'documento', 'telefono', 'correo', 'nacionalidad', 'activo')->orderBy('apellido')->cursor()->map(fn ($h) => $h->toArray()),
            'reservas' => Reserva::query()->with(['huesped:id,nombre,apellido', 'habitacion:id,numero'])->orderBy('fecha_entrada')->cursor()->map(fn ($r) => [
                'id' => $r->id, 'huesped' => $r->huesped->nombre.' '.$r->huesped->apellido, 'habitacion' => $r->habitacion->numero,
                'entrada' => $r->fecha_entrada->toDateString(), 'salida' => $r->fecha_salida->toDateString(), 'noches' => $r->noches,
                'costo_hospedaje' => $r->costo_hospedaje, 'consumos' => $r->total_consumos, 'total' => $r->total, 'estado' => $r->estado,
            ]),
            'consumos' => Consumo::query()->with('reserva:id,habitacion_id')->orderBy('consumido_en')->cursor()->map(fn ($c) => [
                'reserva' => $c->reserva_id, 'categoria' => $c->categoria, 'descripcion' => $c->descripcion, 'cantidad' => $c->cantidad,
                'precio_unitario' => $c->precio_unitario, 'total' => $c->total, 'fecha' => $c->consumido_en?->toDateTimeString(),
            ]),
        };

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            $first = true;
            foreach ($rows as $row) {
                if ($first) {
                    fputcsv($out, array_keys($row));
                    $first = false;
                }
                fputcsv($out, array_map(fn ($v) => is_bool($v) ? ($v ? 'Sí' : 'No') : $v, $row));
            }
            fclose($out);
        }, "$module-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
