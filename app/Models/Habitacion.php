<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habitacion extends Model
{
    public const ESTADOS = ['disponible', 'ocupada', 'limpieza', 'mantenimiento'];

    protected $table = 'habitaciones';

    protected $fillable = ['numero', 'piso', 'tipo_habitacion_id', 'estado', 'activo', 'observaciones'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoHabitacion::class, 'tipo_habitacion_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
