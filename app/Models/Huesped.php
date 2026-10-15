<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Huesped extends Model
{
    protected $table = 'huespedes';

    protected $fillable = ['nombre', 'apellido', 'documento', 'telefono', 'correo', 'nacionalidad', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
