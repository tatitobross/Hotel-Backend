<?php

namespace App\Http\Controllers;

use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservaController extends Controller
{
    public function __construct(private ReservaService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = Reserva::query()->with($this->relations())->latest('fecha_entrada');
        if ($estado = $request->string('estado')->trim()->toString()) {
            $query->where('estado', $estado);
        }
        if ($id = $request->integer('habitacion_id')) {
            $query->where('habitacion_id', $id);
        }
        if ($id = $request->integer('huesped_id')) {
            $query->where('huesped_id', $id);
        }
        if ($request->filled('desde')) {
            $query->where('fecha_salida', '>=', $request->date('desde')->toDateString());
        }
        if ($request->filled('hasta')) {
            $query->where('fecha_entrada', '<=', $request->date('hasta')->toDateString());
        }
        if ($search = $request->string('buscar')->trim()->toString()) {
            $query->whereHas('huesped', fn ($q) => $q->where('nombre', 'like', "%{$search}%")->orWhere('apellido', 'like', "%{$search}%")->orWhere('documento', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate($request->integer('por_pagina', 15)));
    }

    public function show(Reserva $reserva): JsonResponse
    {
        return response()->json(['data' => $reserva->load([...$this->relations(), 'consumos'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true));

        $reserva = DB::transaction(function () use ($data, $request): Reserva {
            $habitacion = Habitacion::query()->lockForUpdate()->with('tipo')->findOrFail($data['habitacion_id']);
            $this->validarHabitacionYHuesped($habitacion, (int) $data['huesped_id'], (int) $data['personas']);
            $this->service->assertSinCruce($habitacion->id, $data['fecha_entrada'], $data['fecha_salida']);

            $calculo = $this->service->calcular($habitacion, $data['fecha_entrada'], $data['fecha_salida']);

            return Reserva::create([
                ...$data,
                ...$calculo,
                'total_consumos' => 0,
                'total' => $calculo['costo_hospedaje'],
                'estado' => 'reservada',
                'creada_por' => $request->user()->id,
            ]);
        });

        return response()->json(['message' => 'Reserva creada.', 'data' => $reserva->load($this->relations())], 201);
    }

    /** Solo reservas aún no iniciadas; recalcula noches y costo con los nuevos datos. */
    public function update(Request $request, Reserva $reserva): JsonResponse
    {
        $data = $request->validate($this->rules(false));

        $reserva = DB::transaction(function () use ($data, $reserva): Reserva {
            $reserva = Reserva::query()->lockForUpdate()->findOrFail($reserva->id);
            if ($reserva->estado !== 'reservada') {
                abort(422, 'Solo se pueden modificar reservas en estado reservada.');
            }
            $habitacion = Habitacion::query()->lockForUpdate()->with('tipo')->findOrFail($data['habitacion_id']);
            $this->validarHabitacionYHuesped($habitacion, (int) $data['huesped_id'], (int) $data['personas']);
            $this->service->assertSinCruce($habitacion->id, $data['fecha_entrada'], $data['fecha_salida'], $reserva->id);

            $calculo = $this->service->calcular($habitacion, $data['fecha_entrada'], $data['fecha_salida']);
            $reserva->update([...$data, ...$calculo, 'total' => $calculo['costo_hospedaje'] + (float) $reserva->total_consumos]);

            return $reserva->fresh();
        });

        return response()->json(['message' => 'Reserva actualizada.', 'data' => $reserva->load($this->relations())]);
    }

    public function cancelar(Request $request, Reserva $reserva): JsonResponse
    {
        $data = $request->validate(['motivo' => ['nullable', 'string', 'max:300']]);
        DB::transaction(function () use ($reserva, $data): void {
            $reserva = Reserva::query()->lockForUpdate()->findOrFail($reserva->id);
            if ($reserva->estado !== 'reservada') {
                abort(422, 'Solo se pueden cancelar reservas en estado reservada.');
            }
            $reserva->update([
                'estado' => 'cancelada',
                'observaciones' => trim(($reserva->observaciones ? $reserva->observaciones.' | ' : '').'Cancelada'.(isset($data['motivo']) ? ': '.$data['motivo'] : '')),
            ]);
        });

        return response()->json(['message' => 'Reserva cancelada; las fechas quedan libres.', 'data' => $reserva->fresh()->load($this->relations())]);
    }

    public function checkin(Reserva $reserva): JsonResponse
    {
        $reserva = DB::transaction(function () use ($reserva): Reserva {
            $reserva = Reserva::query()->lockForUpdate()->findOrFail($reserva->id);
            $habitacion = Habitacion::query()->lockForUpdate()->findOrFail($reserva->habitacion_id);
            if ($reserva->estado !== 'reservada') {
                abort(422, 'Solo se puede hacer check-in de una reserva en estado reservada.');
            }
            if (today()->lt($reserva->fecha_entrada)) {
                abort(422, 'Aún no es la fecha de entrada de la reserva ('.$reserva->fecha_entrada->toDateString().').');
            }
            if (today()->gte($reserva->fecha_salida)) {
                abort(422, 'La fecha de salida de la reserva ya pasó; cree una nueva reserva.');
            }
            if (! $habitacion->activo || $habitacion->estado !== 'disponible') {
                abort(422, "La habitación {$habitacion->numero} no está disponible (estado: {$habitacion->estado}).");
            }
            $habitacion->update(['estado' => 'ocupada']);
            $reserva->update(['estado' => 'en_estadia', 'check_in_en' => now()]);

            return $reserva->fresh();
        });

        return response()->json(['message' => 'Check-in registrado y habitación marcada como ocupada.', 'data' => $reserva->load($this->relations())]);
    }

    public function checkout(Reserva $reserva): JsonResponse
    {
        $reserva = DB::transaction(function () use ($reserva): Reserva {
            $reserva = Reserva::query()->lockForUpdate()->findOrFail($reserva->id);
            if ($reserva->estado !== 'en_estadia') {
                abort(422, 'Solo se puede hacer check-out de una reserva en estadía.');
            }
            $this->service->recalcularTotales($reserva);
            $reserva->update(['estado' => 'finalizada', 'check_out_en' => now()]);
            Habitacion::query()->lockForUpdate()->findOrFail($reserva->habitacion_id)->update(['estado' => 'limpieza']);

            return $reserva->fresh();
        });

        return response()->json(['message' => 'Check-out registrado. La habitación pasa a limpieza.', 'data' => $reserva->load([...$this->relations(), 'consumos'])]);
    }

    public function destroy(Reserva $reserva): JsonResponse
    {
        if (! in_array($reserva->estado, ['reservada', 'cancelada'], true)) {
            return response()->json(['message' => 'Solo se eliminan reservas reservadas o canceladas, sin consumos.'], 422);
        }
        if ($reserva->consumos()->exists()) {
            return response()->json(['message' => 'La reserva tiene consumos registrados.'], 422);
        }
        $reserva->delete();

        return response()->json(['message' => 'Reserva eliminada.']);
    }

    private function rules(bool $creando): array
    {
        return [
            'huesped_id' => ['required', 'integer', 'exists:huespedes,id'],
            'habitacion_id' => ['required', 'integer', 'exists:habitaciones,id'],
            'fecha_entrada' => ['required', 'date_format:Y-m-d', $creando ? 'after_or_equal:today' : 'date_format:Y-m-d'],
            'fecha_salida' => ['required', 'date_format:Y-m-d', 'after:fecha_entrada'],
            'personas' => ['required', 'integer', 'min:1', 'max:20'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function validarHabitacionYHuesped(Habitacion $habitacion, int $huespedId, int $personas): void
    {
        if (! $habitacion->activo || ! $habitacion->tipo->activo) {
            abort(422, 'La habitación o su tipo está inactivo.');
        }
        if (! Huesped::query()->whereKey($huespedId)->where('activo', true)->exists()) {
            abort(422, 'El huésped está inactivo.');
        }
        if ($personas > $habitacion->tipo->capacidad) {
            abort(422, "La habitación admite máximo {$habitacion->tipo->capacidad} persona(s).");
        }
    }

    private function relations(): array
    {
        return ['huesped:id,nombre,apellido,documento,telefono', 'habitacion.tipo', 'creadaPor'];
    }
}
