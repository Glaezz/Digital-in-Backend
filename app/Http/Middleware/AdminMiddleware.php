<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth("sanctum")->user();

        if (!$user) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You need to log in first.'
            ], 403);
        }

        if ((is_null($user->authority) || $user->authority !== 'admin')) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not an administrator.'
            ], 403);
        }

        return $next($request);
    }
}
