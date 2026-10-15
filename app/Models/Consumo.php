<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consumo extends Model
{
    protected $fillable = ['reserva_id', 'categoria', 'descripcion', 'cantidad', 'precio_unitario', 'total', 'consumido_en', 'registrado_por'];

    protected function casts(): array
    {
        return ['consumido_en' => 'datetime', 'precio_unitario' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por')->select('id', 'username', 'name');
    }
}
