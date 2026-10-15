<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_habitacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->unsignedSmallInteger('capacidad')->default(2);
            $table->decimal('precio_noche', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('habitaciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->unsignedSmallInteger('piso')->default(1);
            $table->foreignId('tipo_habitacion_id')->constrained('tipos_habitacion');
            $table->string('estado', 20)->default('disponible'); // disponible|ocupada|limpieza|mantenimiento
            $table->boolean('activo')->default(true);
            $table->string('observaciones', 255)->nullable();
            $table->timestamps();
            $table->index('estado');
        });

        Schema::create('huespedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('apellido', 80);
            $table->string('documento', 30)->unique();
            $table->string('telefono', 30);
            $table->string('correo', 150)->nullable()->unique();
            $table->string('nacionalidad', 60)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('huesped_id')->constrained('huespedes');
            $table->foreignId('habitacion_id')->constrained('habitaciones');
            $table->date('fecha_entrada');
            $table->date('fecha_salida');
            $table->unsignedSmallInteger('personas')->default(1);
            $table->unsignedSmallInteger('noches');
            $table->decimal('precio_noche', 10, 2);
            $table->decimal('costo_hospedaje', 12, 2);
            $table->decimal('total_consumos', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('estado', 20)->default('reservada'); // reservada|en_estadia|finalizada|cancelada
            $table->dateTime('check_in_en')->nullable();
            $table->dateTime('check_out_en')->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['habitacion_id', 'fecha_entrada', 'fecha_salida']);
            $table->index('estado');
        });

        Schema::create('consumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas');
            $table->string('categoria', 20)->default('otro'); // restaurante|bar|lavanderia|minibar|spa|otro
            $table->string('descripcion', 150);
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('total', 12, 2);
            $table->dateTime('consumido_en');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumos');
        Schema::dropIfExists('reservas');
        Schema::dropIfExists('huespedes');
        Schema::dropIfExists('habitaciones');
        Schema::dropIfExists('tipos_habitacion');
    }
};
