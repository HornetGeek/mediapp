<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $ability): Response
    {
        if (!$request->user() || !$request->user()->currentAccessToken()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
    
        // التحقق من أن الـ token لديه الـ ability المطلوبة
        if (!$request->user()->currentAccessToken()->can($ability)) {
            return response()->json(['message' => 'Unauthorized for this action'], 403);
        }
    
        return $next($request);
    }
}
