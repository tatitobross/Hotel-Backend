<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // status y role_id NO son asignables en masa: solo un administrador los asigna de forma explícita.
    protected $fillable = ['username', 'password', 'name', 'gender', 'birth', 'email'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth' => 'date',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function reservasCreadas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'creada_por');
    }

    /** Datos de perfil que se exponen por la API (nunca password ni tokens). */
    public function perfil(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'gender' => $this->gender,
            'birth' => $this->birth?->toDateString(),
            'email' => $this->email,
            'status' => $this->status,
            'role' => $this->role?->role,
        ];
    }
}
