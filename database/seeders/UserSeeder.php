<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->saveUser('Admin', 'Administrador del Hotel', 'admin@hotel.test', 'Administrador');
        $this->saveUser('Recepcion', 'Recepcionista de Turno', 'recepcion@hotel.test', 'Recepcionista');
    }

    private function saveUser(string $username, string $name, string $email, string $roleName): void
    {
        $user = User::firstOrNew(['username' => $username]);
        if (! $user->exists) {
            $user->password = Hash::make('Hola123');
        }
        $user->name = $name;
        $user->gender = 'No especificado';
        $user->birth = '2000-01-01';
        $user->email = $email;
        $user->status = 'active';
        $user->role_id = Role::where('role', $roleName)->value('id');
        $user->save();
    }
}
