<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return new JsonResponse([
                'message' => 'Необходимо авторизоваться.',
            ], 401);
        }

        if (!$user->hasPermission($permission)) {
            return new JsonResponse([
                'message' => 'У вас нет доступа к этому действию.',
            ], 403);
        }

        return $next($request);
    }
}
