<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsumoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HabitacionController;
use App\Http\Controllers\HuespedController;
use App\Http\Controllers\PrivilegeController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TipoHabitacionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/user', [AuthController::class, 'user'])->middleware('abilities:profile:read');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Administrador y Recepcionista
    Route::middleware('role:Administrador,Recepcionista')->group(function (): void {
        Route::get('/tipos-habitacion', [TipoHabitacionController::class, 'index']);
        Route::get('/tipos-habitacion/{tipo}', [TipoHabitacionController::class, 'show']);

        Route::get('/habitaciones/disponibles', [HabitacionController::class, 'disponibles']);
        Route::get('/habitaciones', [HabitacionController::class, 'index']);
        Route::get('/habitaciones/{habitacion}', [HabitacionController::class, 'show']);
        Route::patch('/habitaciones/{habitacion}/estado', [HabitacionController::class, 'cambiarEstado']);

        Route::apiResource('huespedes', HuespedController::class)->except('destroy')->parameters(['huespedes' => 'huesped']);

        Route::get('/reservas', [ReservaController::class, 'index']);
        Route::post('/reservas', [ReservaController::class, 'store']);
        Route::get('/reservas/{reserva}', [ReservaController::class, 'show']);
        Route::put('/reservas/{reserva}', [ReservaController::class, 'update']);
        Route::post('/reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar']);
        Route::post('/reservas/{reserva}/checkin', [ReservaController::class, 'checkin']);
        Route::post('/reservas/{reserva}/checkout', [ReservaController::class, 'checkout']);

        Route::get('/consumos', [ConsumoController::class, 'index']);
        Route::get('/reservas/{reserva}/consumos', [ConsumoController::class, 'index']);
        Route::post('/reservas/{reserva}/consumos', [ConsumoController::class, 'store']);
    });

    // Solo Administrador
    Route::middleware('role:Administrador')->group(function (): void {
        Route::post('/tipos-habitacion', [TipoHabitacionController::class, 'store']);
        Route::put('/tipos-habitacion/{tipo}', [TipoHabitacionController::class, 'update']);
        Route::delete('/tipos-habitacion/{tipo}', [TipoHabitacionController::class, 'destroy']);

        Route::post('/habitaciones', [HabitacionController::class, 'store']);
        Route::put('/habitaciones/{habitacion}', [HabitacionController::class, 'update']);
        Route::delete('/habitaciones/{habitacion}', [HabitacionController::class, 'destroy']);

        Route::delete('/huespedes/{huesped}', [HuespedController::class, 'destroy']);
        Route::delete('/reservas/{reserva}', [ReservaController::class, 'destroy']);
        Route::delete('/consumos/{consumo}', [ConsumoController::class, 'destroy']);

        Route::get('/roles', [RoleController::class, 'index']);
        Route::apiResource('usuarios', UserController::class)->parameters(['usuarios' => 'usuario']);
        Route::get('/privileges', [PrivilegeController::class, 'index']);
        Route::post('/privileges', [PrivilegeController::class, 'store']);
        Route::get('/exportar/{module}', [ExportController::class, 'csv']);
    });
});
