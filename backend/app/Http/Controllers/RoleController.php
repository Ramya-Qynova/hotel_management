<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * List all roles in the system.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::orderBy('id')->get();

        return ApiResponse::success($roles, 'Roles retrieved successfully.');
    }
}

