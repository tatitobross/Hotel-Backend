# API Hotel (Laravel 12 + Sanctum)

API REST para un **hotel pequeño**. Roles: **Administrador** y **Recepcionista**.
Autenticación con tokens Bearer de Laravel Sanctum (sin registro público).

## Instalación

```bash
composer install
cp .env.example .env          # ajuste DB_DATABASE, DB_USERNAME, DB_PASSWORD (MySQL: base "hotel")
php artisan key:generate
php artisan migrate --seed
php artisan serve             # http://localhost:8000
php artisan test              # 16 pruebas
```

Usuarios de los seeders (contraseña `Hola123`): `Admin` (Administrador) y `Recepcion` (Recepcionista).
Los seeders son repetibles (no duplican datos). Cargan 4 tipos, 8 habitaciones y 3 huéspedes de ejemplo.

## Autenticación

| Método y ruta | Descripción |
|---|---|
| `POST /api/login` | `username`, `password`, `device_name`. Máx. 5 por minuto. Devuelve `access_token` (dura 1 hora), `expires_at` y `user`. |
| `GET /api/user` | Perfil del usuario autenticado. |
| `POST /api/logout` | Revoca el token actual. |
| `PUT /api/profile`, `PUT /api/profile/password` | Actualizar perfil / cambiar contraseña. |

Enviar `Authorization: Bearer <access_token>` y `Accept: application/json`.

## CRUDs y permisos

| Módulo | Rutas | Administrador | Recepcionista |
|---|---|---|---|
| Tipos de habitación | `/api/tipos-habitacion` | CRUD | Solo lectura |
| Habitaciones | `/api/habitaciones`, `/habitaciones/disponibles`, `PATCH /habitaciones/{id}/estado` | CRUD | Lectura, disponibilidad y cambio de estado |
| Huéspedes | `/api/huespedes` | CRUD | Crear, ver, editar |
| Reservas | `/api/reservas`, `/{id}/cancelar`, `/{id}/checkin`, `/{id}/checkout` | Todo | Todo menos eliminar |
| Consumos | `/api/reservas/{id}/consumos`, `/api/consumos` | Todo | Registrar y consultar |
| Usuarios | `/api/usuarios`, `/api/roles`, `/api/privileges` | CRUD | — |
| Exportar CSV | `/api/exportar/{habitaciones, huespedes, reservas, consumos}` | Sí | — |
| Dashboard | `/api/dashboard` | Sí | Sí |

Listados paginados (`?por_pagina=15`) con filtros: `buscar`, `estado`, `desde`, `hasta`, `habitacion_id`, `huesped_id`, `categoria`.

## Reglas de negocio

1. **Evitar reservas cruzadas.** Una habitación no puede tener dos reservas activas (`reservada` o `en_estadia`) que se solapen. La noche de salida queda libre: se puede reservar entrando el mismo día que otro huésped sale. Se valida dentro de una transacción con bloqueo de la habitación. Una reserva cancelada libera sus fechas.
2. **Noches y costo de hospedaje.** `noches = fecha_salida - fecha_entrada` (la salida debe ser posterior a la entrada y la entrada no puede ser anterior a hoy). `costo_hospedaje = noches × precio_noche` del tipo de habitación. El precio se guarda en la reserva, así que cambios posteriores de tarifa no la afectan. Se recalcula al modificar una reserva (solo en estado `reservada`). También se valida la capacidad del tipo de habitación.
3. **Disponibilidad.**
   - `GET /api/habitaciones/disponibles?fecha_entrada=&fecha_salida=&personas=&tipo_habitacion_id=` devuelve las habitaciones libres para el rango.
   - **Check-in:** solo desde la fecha de entrada y con la habitación `disponible`; la habitación pasa a `ocupada`.
   - **Check-out:** suma los consumos y calcula `total = hospedaje + consumos`; la habitación pasa a `limpieza`. Recepción la marca `disponible` con `PATCH /habitaciones/{id}/estado`.
   - `ocupada` solo se asigna por check-in; no se cambia a mano.
4. **Consumos.** Solo se registran mientras el huésped está en estadía (después del check-in y antes del check-out). Cada consumo recalcula `total_consumos` y `total` de la reserva.
5. **Integridad.** No se eliminan tipos con habitaciones, habitaciones ni huéspedes con reservas, ni reservas con consumos. Los usuarios inactivos pierden sus tokens.

Estados de reserva: `reservada → en_estadia → finalizada` (o `cancelada` desde `reservada`).

## Postman

Importe `Hotel_API_Sanctum.postman_collection.json`. La carpeta **05 Reservas** recorre el flujo completo (crear, cruce 422, check-in, consumos, check-out).
