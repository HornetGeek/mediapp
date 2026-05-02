<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRepresentativeIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $representative = $request->user();

        if (!$representative || ($representative->registration_status ?? 'active') !== 'active') {
            return ApiResponse::sendResponse(403, 'Representative account is pending company approval', [
                'error_code' => 'REP_PENDING_APPROVAL',
            ]);
        }

        return $next($request);
    }
}
