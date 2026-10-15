<?php

namespace Tests\Feature;

use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\TipoHabitacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HotelApiTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol, string $username = null): User
    {
        $role = Role::firstOrCreate(['role' => $rol]);
        $user = new User(['username' => $username ?? strtolower($rol), 'password' => 'Hola123', 'name' => $rol, 'gender' => 'No especificado', 'birth' => '2000-01-01', 'email' => strtolower($rol).'@hotel.test']);
        $user->role_id = $role->id;
        $user->status = 'active';
        $user->save();

        return $user;
    }

    private function entrar(string $rol = 'Administrador'): User
    {
        $u = $this->usuario($rol);
        Sanctum::actingAs($u, ['profile:read', 'hotel:manage']);

        return $u;
    }

    private function habitacion(float $precio = 400, int $capacidad = 2, string $numero = '101'): Habitacion
    {
        $tipo = TipoHabitacion::firstOrCreate(['nombre' => "Tipo $precio"], ['capacidad' => $capacidad, 'precio_noche' => $precio]);

        return Habitacion::create(['numero' => $numero, 'piso' => 1, 'tipo_habitacion_id' => $tipo->id]);
    }

    private function huesped(string $doc = '123'): Huesped
    {
        return Huesped::create(['nombre' => 'Ana', 'apellido' => 'López', 'documento' => $doc, 'telefono' => '5555']);
    }

    private function reservar(Habitacion $h, Huesped $g, string $entrada, string $salida, int $personas = 2)
    {
        return $this->postJson('/api/reservas', ['huesped_id' => $g->id, 'habitacion_id' => $h->id, 'fecha_entrada' => $entrada, 'fecha_salida' => $salida, 'personas' => $personas]);
    }

    // ---------- Autenticación ----------

    public function test_login_ok_y_errores(): void
    {
        $this->usuario('Administrador', 'Admin');
        $this->postJson('/api/login', ['username' => 'Admin', 'password' => 'Hola123', 'device_name' => 'postman'])
            ->assertOk()->assertJsonStructure(['access_token', 'expires_at', 'user' => ['id', 'username', 'role']])
            ->assertJsonMissingPath('user.password');
        $this->postJson('/api/login', [])->assertStatus(422)->assertJsonValidationErrors(['username', 'password', 'device_name']);
        $this->postJson('/api/login', ['username' => 'Admin', 'password' => 'mala', 'device_name' => 'x'])->assertStatus(401);
        $this->postJson('/api/login', ['username' => 'nadie', 'password' => 'x', 'device_name' => 'x'])->assertStatus(401);
        $this->postJson('/api/register', [])->assertStatus(404);
    }

    public function test_usuario_inactivo_no_entra(): void
    {
        $u = $this->usuario('Recepcionista', 'Recep');
        $u->status = 'inactive';
        $u->save();
        $this->postJson('/api/login', ['username' => 'Recep', 'password' => 'Hola123', 'device_name' => 'x'])->assertStatus(403);
    }

    public function test_sin_token_401_y_logout_revoca(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
        $this->usuario('Administrador', 'Admin');
        $token = $this->postJson('/api/login', ['username' => 'Admin', 'password' => 'Hola123', 'device_name' => 'x'])->json('access_token');
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('user.username', 'Admin');
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_recepcionista_no_accede_a_modulos_de_administrador(): void
    {
        $this->entrar('Recepcionista');
        $this->getJson('/api/usuarios')->assertForbidden();
        $this->postJson('/api/habitaciones', [])->assertForbidden();
        $this->postJson('/api/tipos-habitacion', [])->assertForbidden();
        $this->getJson('/api/exportar/reservas')->assertForbidden();
        $this->getJson('/api/habitaciones')->assertOk();
    }

    // ---------- CRUDs ----------

    public function test_crud_tipos_y_habitaciones_con_borrado_protegido(): void
    {
        $this->entrar();
        $tipo = $this->postJson('/api/tipos-habitacion', ['nombre' => 'Doble', 'capacidad' => 2, 'precio_noche' => 400])->assertCreated()->json('data');
        $this->postJson('/api/tipos-habitacion', ['nombre' => 'Doble', 'capacidad' => 2, 'precio_noche' => 400])->assertStatus(422);
        $hab = $this->postJson('/api/habitaciones', ['numero' => '101', 'piso' => 1, 'tipo_habitacion_id' => $tipo['id']])->assertCreated()->json('data');
        $this->deleteJson("/api/tipos-habitacion/{$tipo['id']}")->assertStatus(422);
        $this->putJson("/api/habitaciones/{$hab['id']}", ['numero' => '101', 'piso' => 2, 'tipo_habitacion_id' => $tipo['id']])->assertOk()->assertJsonPath('data.piso', 2);
        $this->deleteJson("/api/habitaciones/{$hab['id']}")->assertOk();
        $this->deleteJson("/api/tipos-habitacion/{$tipo['id']}")->assertOk();
    }

    public function test_crud_huespedes_y_borrado_protegido(): void
    {
        $this->entrar();
        $g = $this->postJson('/api/huespedes', ['nombre' => 'Juan', 'apellido' => 'Pérez', 'documento' => 'D1', 'telefono' => '1'])->assertCreated()->json('data');
        $this->postJson('/api/huespedes', ['nombre' => 'Otro', 'apellido' => 'X', 'documento' => 'D1', 'telefono' => '1'])->assertStatus(422);
        $this->getJson('/api/huespedes?buscar=Juan')->assertOk()->assertJsonPath('total', 1);
        $h = $this->habitacion();
        $this->reservar($h, Huesped::find($g['id']), today()->addDay()->toDateString(), today()->addDays(3)->toDateString())->assertCreated();
        $this->deleteJson("/api/huespedes/{$g['id']}")->assertStatus(422);
    }

    // ---------- Reglas de reservas ----------

    public function test_calcula_noches_y_costo(): void
    {
        $this->entrar();
        $h = $this->habitacion(400);
        $r = $this->reservar($h, $this->huesped(), today()->addDays(2)->toDateString(), today()->addDays(5)->toDateString())
            ->assertCreated()->assertJsonPath('data.noches', 3)->assertJsonPath('data.estado', 'reservada')->json('data');
        $this->assertEquals(1200.00, (float) $r['costo_hospedaje']);
        $this->assertEquals(1200.00, (float) $r['total']);
    }

    public function test_evita_reservas_cruzadas_pero_permite_noche_de_salida(): void
    {
        $this->entrar();
        $h = $this->habitacion();
        $g = $this->huesped();
        $d = fn (int $n) => today()->addDays($n)->toDateString();
        $this->reservar($h, $g, $d(2), $d(5))->assertCreated();
        // Cruces: dentro, solapa inicio, solapa fin, envuelve
        $this->reservar($h, $g, $d(3), $d(4))->assertStatus(422);
        $this->reservar($h, $g, $d(1), $d(3))->assertStatus(422);
        $this->reservar($h, $g, $d(4), $d(7))->assertStatus(422);
        $this->reservar($h, $g, $d(1), $d(8))->assertStatus(422);
        // Salida el mismo día que entra otra: permitido
        $this->reservar($h, $g, $d(5), $d(6))->assertCreated();
        $this->reservar($h, $g, $d(1), $d(2))->assertCreated();
    }

    public function test_reserva_cancelada_libera_las_fechas(): void
    {
        $this->entrar();
        $h = $this->habitacion();
        $g = $this->huesped();
        $a = today()->addDays(2)->toDateString();
        $b = today()->addDays(4)->toDateString();
        $id = $this->reservar($h, $g, $a, $b)->assertCreated()->json('data.id');
        $this->reservar($h, $g, $a, $b)->assertStatus(422);
        $this->postJson("/api/reservas/$id/cancelar", ['motivo' => 'Cambio de planes'])->assertOk()->assertJsonPath('data.estado', 'cancelada');
        $this->reservar($h, $g, $a, $b)->assertCreated();
    }

    public function test_validaciones_de_fechas_capacidad_y_estado(): void
    {
        $this->entrar();
        $h = $this->habitacion(400, 2);
        $g = $this->huesped();
        $this->reservar($h, $g, today()->subDay()->toDateString(), today()->addDay()->toDateString())->assertStatus(422);
        $this->reservar($h, $g, today()->addDays(3)->toDateString(), today()->addDays(3)->toDateString())->assertStatus(422);
        $this->reservar($h, $g, today()->addDays(3)->toDateString(), today()->addDays(2)->toDateString())->assertStatus(422);
        $this->reservar($h, $g, today()->addDay()->toDateString(), today()->addDays(2)->toDateString(), 5)->assertStatus(422);
        $h->update(['activo' => false]);
        $this->reservar($h, $g, today()->addDay()->toDateString(), today()->addDays(2)->toDateString())->assertStatus(422);
    }

    public function test_modificar_reserva_recalcula_y_respeta_cruces(): void
    {
        $this->entrar();
        $h = $this->habitacion(400);
        $g = $this->huesped();
        $d = fn (int $n) => today()->addDays($n)->toDateString();
        $id = $this->reservar($h, $g, $d(2), $d(4))->assertCreated()->json('data.id');
        $otra = $this->reservar($h, $g, $d(6), $d(8))->assertCreated()->json('data.id');
        $base = ['huesped_id' => $g->id, 'habitacion_id' => $h->id, 'personas' => 2];
        $r = $this->putJson("/api/reservas/$id", [...$base, 'fecha_entrada' => $d(2), 'fecha_salida' => $d(5)])->assertOk()->assertJsonPath('data.noches', 3)->json('data');
        $this->assertEquals(1200.00, (float) $r['total']);
        $this->putJson("/api/reservas/$id", [...$base, 'fecha_entrada' => $d(2), 'fecha_salida' => $d(7)])->assertStatus(422);
        $this->putJson("/api/reservas/$otra", [...$base, 'fecha_entrada' => $d(6), 'fecha_salida' => $d(8)])->assertOk();
    }

    // ---------- Disponibilidad ----------

    public function test_disponibilidad_por_fechas_y_flujo_checkin_checkout(): void
    {
        $this->entrar();
        $h1 = $this->habitacion(400, 2, '101');
        $h2 = $this->habitacion(400, 2, '102');
        $g = $this->huesped();
        $hoy = today()->toDateString();
        $man = today()->addDays(2)->toDateString();

        $this->getJson("/api/habitaciones/disponibles?fecha_entrada=$hoy&fecha_salida=$man")->assertOk()->assertJsonCount(2, 'data');
        $id = $this->reservar($h1, $g, $hoy, $man)->assertCreated()->json('data.id');
        $this->getJson("/api/habitaciones/disponibles?fecha_entrada=$hoy&fecha_salida=$man")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.numero', '102');

        $this->postJson("/api/reservas/$id/checkin")->assertOk()->assertJsonPath('data.estado', 'en_estadia');
        $this->assertSame('ocupada', $h1->fresh()->estado);
        $this->postJson("/api/reservas/$id/checkin")->assertStatus(422);

        $this->postJson("/api/reservas/$id/consumos", ['categoria' => 'restaurante', 'descripcion' => 'Cena', 'cantidad' => 2, 'precio_unitario' => 75.50])->assertCreated();
        $this->postJson("/api/reservas/$id/consumos", ['categoria' => 'minibar', 'descripcion' => 'Agua', 'cantidad' => 3, 'precio_unitario' => 10])->assertCreated();
        $this->postJson("/api/reservas/$id/cancelar")->assertStatus(422);

        $r = $this->postJson("/api/reservas/$id/checkout")->assertOk()->assertJsonPath('data.estado', 'finalizada')->json('data');
        $this->assertEquals(181.00, (float) $r['total_consumos']);
        $this->assertEquals(800.00, (float) $r['costo_hospedaje']);
        $this->assertEquals(981.00, (float) $r['total']);
        $this->assertSame('limpieza', $h1->fresh()->estado);
        $this->postJson("/api/reservas/$id/checkout")->assertStatus(422);
        $this->postJson("/api/reservas/$id/consumos", ['categoria' => 'bar', 'descripcion' => 'Tarde', 'cantidad' => 1, 'precio_unitario' => 5])->assertStatus(422);

        $this->patchJson("/api/habitaciones/{$h1->id}/estado", ['estado' => 'disponible'])->assertOk();
        $this->assertSame('disponible', $h1->fresh()->estado);
    }

    public function test_checkin_bloqueado_antes_de_fecha_o_con_habitacion_no_disponible(): void
    {
        $this->entrar();
        $h = $this->habitacion();
        $g = $this->huesped();
        $futura = $this->reservar($h, $g, today()->addDays(3)->toDateString(), today()->addDays(4)->toDateString())->assertCreated()->json('data.id');
        $this->postJson("/api/reservas/$futura/checkin")->assertStatus(422);

        $hoy = $this->reservar($this->habitacion(400, 2, '102'), $g, today()->toDateString(), today()->addDay()->toDateString())->assertCreated();
        Habitacion::where('numero', '102')->update(['estado' => 'mantenimiento']);
        $this->postJson('/api/reservas/'.$hoy->json('data.id').'/checkin')->assertStatus(422);
    }

    public function test_no_se_cambia_a_mano_el_estado_de_habitacion_ocupada(): void
    {
        $this->entrar();
        $h = $this->habitacion();
        $id = $this->reservar($h, $this->huesped(), today()->toDateString(), today()->addDay()->toDateString())->json('data.id');
        $this->postJson("/api/reservas/$id/checkin")->assertOk();
        $this->patchJson("/api/habitaciones/{$h->id}/estado", ['estado' => 'disponible'])->assertStatus(422);
        $this->patchJson("/api/habitaciones/{$h->id}/estado", ['estado' => 'ocupada'])->assertStatus(422);
    }

    public function test_dashboard_y_exportar(): void
    {
        $this->entrar();
        $h = $this->habitacion();
        $id = $this->reservar($h, $this->huesped(), today()->toDateString(), today()->addDay()->toDateString())->json('data.id');
        $this->postJson("/api/reservas/$id/checkin")->assertOk();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.habitaciones_ocupadas', 1)->assertJsonPath('data.ocupacion_porcentaje', 100);
        $this->get('/api/exportar/reservas')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->getJson('/api/exportar/otro')->assertStatus(404);
    }

    public function test_crud_usuarios_solo_admin_y_desactivar_revoca_tokens(): void
    {
        $admin = $this->entrar();
        $rol = Role::firstOrCreate(['role' => 'Recepcionista']);
        $u = $this->postJson('/api/usuarios', ['username' => 'rec1', 'name' => 'Rec Uno', 'email' => 'rec1@hotel.test', 'gender' => 'Mujer', 'birth' => '1998-02-02', 'role_id' => $rol->id, 'status' => 'active', 'password' => 'Clave12345'])->assertCreated()->json('data');
        $this->assertArrayNotHasKey('password', $u);
        $this->deleteJson("/api/usuarios/{$admin->id}")->assertStatus(422);
        $this->putJson("/api/usuarios/{$u['id']}", ['username' => 'rec1', 'name' => 'Rec Uno', 'email' => 'rec1@hotel.test', 'gender' => 'Mujer', 'birth' => '1998-02-02', 'role_id' => $rol->id, 'status' => 'inactive'])->assertOk();
        $this->deleteJson("/api/usuarios/{$u['id']}")->assertOk();
    }
}
