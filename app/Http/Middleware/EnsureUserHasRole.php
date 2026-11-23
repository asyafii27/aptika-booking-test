<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role?->value;

        if (! in_array($role, $roles, true)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki akses', 403);
        }

        return $next($request);
    }
}
