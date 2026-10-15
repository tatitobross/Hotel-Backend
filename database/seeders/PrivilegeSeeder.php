<?php

namespace Database\Seeders;

use App\Models\Privilege;
use App\Models\User;
use Illuminate\Database\Seeder;

class PrivilegeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::with('role')->get() as $user) {
            $admin = $user->role?->role === 'Administrador';
            Privilege::updateOrCreate(['user_id' => $user->id, 'role_id' => $user->role_id], [
                'save' => 'S', 'update' => 'S',
                'delete' => $admin ? 'S' : 'N',
                'export' => $admin ? 'S' : 'N',
            ]);
        }
    }
}
