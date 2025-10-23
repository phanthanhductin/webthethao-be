<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminAbility
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Chưa đăng nhập -> 401
        if (! $user || ! $user->currentAccessToken()) {
            abort(401, 'Unauthenticated.');
        }

        // Token không có quyền 'admin' -> 403
        if (! $user->tokenCan('admin')) {
            throw new AccessDeniedHttpException('Admin ability required.');
        }

        return $next($request);
    }
}
