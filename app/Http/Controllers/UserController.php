<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()
                ->with('role')
                ->orderBy('id')
                ->get()
        );
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(
            $user->load('role.permissions')
        );
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        $user->update([
            'role_id' => $request->integer('role_id'),
        ]);

        return response()->json(
            $user->fresh()->load('role.permissions')
        );
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'Нельзя удалить самого себя.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Пользователь удалён.',
        ]);
    }
}
