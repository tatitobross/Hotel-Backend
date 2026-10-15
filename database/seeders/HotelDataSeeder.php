<?php

namespace Database\Seeders;

use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\TipoHabitacion;
use Illuminate\Database\Seeder;

class HotelDataSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [];
        foreach ([
            ['Sencilla', 'Una cama individual, baño privado', 1, 250],
            ['Doble', 'Dos camas individuales o una matrimonial', 2, 400],
            ['Familiar', 'Dos camas matrimoniales, ideal para familias', 4, 650],
            ['Suite', 'Sala, cama king y balcón', 3, 900],
        ] as [$nombre, $desc, $cap, $precio]) {
            $tipos[$nombre] = TipoHabitacion::updateOrCreate(['nombre' => $nombre], ['descripcion' => $desc, 'capacidad' => $cap, 'precio_noche' => $precio, 'activo' => true]);
        }

        foreach ([
            ['101', 1, 'Sencilla'], ['102', 1, 'Sencilla'], ['103', 1, 'Doble'],
            ['201', 2, 'Doble'], ['202', 2, 'Familiar'], ['203', 2, 'Familiar'],
            ['301', 3, 'Suite'], ['302', 3, 'Doble'],
        ] as [$numero, $piso, $tipo]) {
            Habitacion::updateOrCreate(['numero' => $numero], ['piso' => $piso, 'tipo_habitacion_id' => $tipos[$tipo]->id, 'activo' => true]);
        }

        foreach ([
            ['Ana', 'López', '2456789010101', '5555-0101', 'ana.lopez@correo.test', 'Guatemala'],
            ['Carlos', 'Méndez', '2987654320101', '5555-0202', 'carlos.mendez@correo.test', 'Guatemala'],
            ['Laura', 'Smith', 'P-X1234567', '5555-0303', 'laura.smith@correo.test', 'Estados Unidos'],
        ] as [$nombre, $apellido, $doc, $tel, $correo, $nac]) {
            Huesped::updateOrCreate(['documento' => $doc], ['nombre' => $nombre, 'apellido' => $apellido, 'telefono' => $tel, 'correo' => $correo, 'nacionalidad' => $nac, 'activo' => true]);
        }
    }
}
