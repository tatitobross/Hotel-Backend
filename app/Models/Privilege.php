<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Privilege extends Model
{
    protected $fillable = ['user_id', 'role_id', 'save', 'update', 'delete', 'export'];
    public function user() { return $this->belongsTo(User::class); }
    public function role() { return $this->belongsTo(Role::class); }
}
