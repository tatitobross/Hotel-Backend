<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoHabitacion extends Model
{
    protected $table = 'tipos_habitacion';

    protected $fillable = ['nombre', 'descripcion', 'capacidad', 'precio_noche', 'activo'];

    protected function casts(): array
    {
        return ['precio_noche' => 'decimal:2', 'activo' => 'boolean'];
    }

    public function habitaciones(): HasMany
    {
        return $this->hasMany(Habitacion::class, 'tipo_habitacion_id');
    }
}
