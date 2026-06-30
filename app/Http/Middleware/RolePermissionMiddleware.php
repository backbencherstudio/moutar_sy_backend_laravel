<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RolePermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('api')->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $routeName = $request->route()->getName();

        $except = [
            'admin.logout',
        ];

        if (in_array($routeName, $except)) {
            return $next($request);
        }

      
        if ($routeName && $user->hasPermissionTo($routeName, 'api')) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'You do not have permission to access this route: '.$routeName,
        ], 403);
    }
}
