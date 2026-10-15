<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! in_array($request->user()?->role?->role, $roles, true)) {
            return response()->json(['message' => 'No tiene permiso para esta acción.'], 403);
        }

        return $next($request);
    }
}
