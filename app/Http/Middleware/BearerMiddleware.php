<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class BearerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $userToken = auth("sanctum")->user();
        if ($userToken == null) {
            return response()->json([
                "status" => "error",
                "message" => "Unauthorized access. Please login to continue"
            ], 401);
        }
        $token = $request->bearerToken();
        if ($token != $userToken->getRememberToken()) {
            return response()->json([
                "status" => "error",
                "message" => "Invalid authentication token"
            ], 401);
        }
        return $next($request);
    }
}
