<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reserva extends Model
{
    /** Estados que ocupan la habitación en el calendario. */
    public const ESTADOS_ACTIVOS = ['reservada', 'en_estadia'];

    protected $fillable = [
        'huesped_id', 'habitacion_id', 'fecha_entrada', 'fecha_salida', 'personas', 'noches', 'precio_noche',
        'costo_hospedaje', 'total_consumos', 'total', 'estado', 'check_in_en', 'check_out_en', 'observaciones', 'creada_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrada' => 'date:Y-m-d',
            'fecha_salida' => 'date:Y-m-d',
            'check_in_en' => 'datetime',
            'check_out_en' => 'datetime',
            'precio_noche' => 'decimal:2',
            'costo_hospedaje' => 'decimal:2',
            'total_consumos' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function huesped(): BelongsTo
    {
        return $this->belongsTo(Huesped::class);
    }

    public function habitacion(): BelongsTo
    {
        return $this->belongsTo(Habitacion::class);
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(Consumo::class);
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por')->select('id', 'username', 'name');
    }

    /** Reservas que se cruzan con el rango [entrada, salida): la noche de salida queda libre. */
    public function scopeCruzaCon(Builder $query, string $entrada, string $salida): Builder
    {
        return $query->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->where('fecha_entrada', '<', $salida)
            ->where('fecha_salida', '>', $entrada);
    }
}
