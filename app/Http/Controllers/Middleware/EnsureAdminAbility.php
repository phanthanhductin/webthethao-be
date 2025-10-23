<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureAdminAbility
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Chưa đăng nhập / chưa có token
        if (!$user || !$user->currentAccessToken()) {
            throw new AccessDeniedHttpException('Unauthenticated.');
        }

        // Kiểm tra abilities trên token
        $abilities = (array)($user->currentAccessToken()->abilities ?? []);
        $hasAdminAbility = in_array('*', $abilities, true) || in_array('admin', $abilities, true);

        // Kiểm tra vai trò
        $role = strtolower((string)($user->roles ?? $user->role ?? ''));

        if (!$hasAdminAbility && $role !== 'admin') {
            throw new AccessDeniedHttpException('Forbidden. Admin ability required.');
        }

        return $next($request);
    }
}
