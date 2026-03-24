<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if ($request->isMethod('post') && $request->routeIs('logout')) {
            return $next($request);
        }
    
        if (!Auth::check()) {
            return redirect('/login');
        }
    
        // التحقق من الصلاحيات
        $userRole = Auth::user()->role;
        
        // سوبر أدمن يمكنه الوصول لكل شيء
        if ($userRole === 'super_admin') {
            return $next($request);
        }
        
        // أدمن الشركة يمكنه الوصول فقط إذا كان الطلب خاص بشركته
        if ($userRole === 'admin' && in_array('admin', $roles)) {
            return $next($request);
        }
        
        abort(403, 'Unauthorized action.');
    }
}
