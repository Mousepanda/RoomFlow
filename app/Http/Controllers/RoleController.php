<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Role::query()
                ->with('permissions')
                ->orderBy('id')
                ->get()
        );
    }
}
