<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->status !== 'active') {
            $user->tokens()->delete();

            return response()->json(['message' => 'El usuario no está activo.'], 403);
        }

        return $next($request);
    }
}
