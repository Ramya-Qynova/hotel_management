<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    /**
     * Display a listing of staff accounts with role and status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('role')->orderByDesc('created_at')->orderByDesc('id');

        // Search by name or email
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('role', function ($rq) use ($search) {
                        $rq->where('name', 'like', "%{$search}%")
                            ->orWhere('display_name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $roleFilter = trim($request->input('role'));
            $query->whereHas('role', function ($rq) use ($roleFilter) {
                if (is_numeric($roleFilter)) {
                    $rq->where('id', (int) $roleFilter);
                } else {
                    $rq->where('name', strtoupper($roleFilter));
                }
            });
        }

        // Filter by active status
        if ($request->has('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            if ($status === 'active' || $status === '1' || $status === true) {
                $query->where('is_active', true);
            } elseif ($status === 'inactive' || $status === '0' || $status === false) {
                $query->where('is_active', false);
            }
        }

        $staff = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::success($staff, 'Staff members retrieved successfully.');
    }

    /**
     * Display the specified staff account details.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('role');

        return ApiResponse::success($user, 'Staff account details retrieved.');
    }

    /**
     * Create a new staff account.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $targetRole = Role::findOrFail($validated['role_id']);

        // Only an OWNER can create another OWNER
        if (strtoupper($targetRole->name) === 'OWNER' && ! $currentUser->isOwner()) {
            return ApiResponse::forbidden('Only hotel owners can assign the OWNER role.');
        }

        $newUser = DB::transaction(function () use ($validated, $currentUser, $targetRole) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id' => $targetRole->id,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            AuditService::log(
                'STAFF_CREATED',
                $user,
                null,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $targetRole->name,
                    'is_active' => $user->is_active,
                ],
                $currentUser->id
            );

            return $user;
        });

        $newUser->load('role');

        return ApiResponse::success($newUser, 'Staff account created successfully.', 201);
    }

    /**
     * Update an existing staff account.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        // If target is an owner, only an owner can edit them
        if ($user->isOwner() && ! $currentUser->isOwner()) {
            return ApiResponse::forbidden('Only hotel owners can modify an owner account.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'password' => ['nullable', 'string', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'role' => $user->role?->name,
            'is_active' => $user->is_active,
        ];

        // Role change validations
        if (isset($validated['role_id']) && $validated['role_id'] != $user->role_id) {
            $newRole = Role::findOrFail($validated['role_id']);

            // Only an OWNER can assign the OWNER role
            if (strtoupper($newRole->name) === 'OWNER' && ! $currentUser->isOwner()) {
                return ApiResponse::forbidden('Only hotel owners can assign the OWNER role.');
            }

            // If demoting an OWNER, verify this is not the last active OWNER
            if ($user->isOwner() && strtoupper($newRole->name) !== 'OWNER') {
                $activeOwnerCount = User::whereHas('role', fn ($q) => $q->where('name', 'OWNER'))
                    ->where('is_active', true)
                    ->count();

                if ($activeOwnerCount <= 1) {
                    return ApiResponse::validationError([
                        'role_id' => ['Cannot demote the last active Owner account. At least one active owner must remain.'],
                    ], 'Operation prohibited: Last active owner protection.');
                }
            }
        }

        // If updating active status to false, check last active owner
        if (isset($validated['is_active']) && ! $validated['is_active'] && $user->is_active && $user->isOwner()) {
            $activeOwnerCount = User::whereHas('role', fn ($q) => $q->where('name', 'OWNER'))
                ->where('is_active', true)
                ->count();

            if ($activeOwnerCount <= 1) {
                return ApiResponse::validationError([
                    'is_active' => ['Cannot deactivate the last active Owner account.'],
                ], 'Operation prohibited: Last active owner protection.');
            }
        }

        DB::transaction(function () use ($user, $validated, $oldValues, $currentUser) {
            $updateData = [];
            if (isset($validated['name'])) $updateData['name'] = $validated['name'];
            if (isset($validated['email'])) $updateData['email'] = $validated['email'];
            if (isset($validated['role_id'])) $updateData['role_id'] = $validated['role_id'];
            if (! empty($validated['password'])) $updateData['password'] = Hash::make($validated['password']);
            if (isset($validated['is_active'])) $updateData['is_active'] = $validated['is_active'];

            $user->update($updateData);

            // If account was deactivated, revoke all active sessions and tokens
            if (isset($validated['is_active']) && ! $validated['is_active']) {
                if (Schema::hasTable('personal_access_tokens')) {
                    $user->tokens()->delete();
                }
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
            }

            $user->load('role');

            AuditService::log(
                'STAFF_UPDATED',
                $user,
                $oldValues,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role' => $user->role?->name,
                    'is_active' => $user->is_active,
                ],
                $currentUser->id
            );
        });

        $user->load('role');

        return ApiResponse::success($user, 'Staff account updated successfully.');
    }

    /**
     * Activate or deactivate a staff account safely.
     */
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        // Administrator cannot deactivate an Owner
        if ($user->isOwner() && ! $currentUser->isOwner()) {
            return ApiResponse::forbidden('Administrators cannot change the status of an Owner account.');
        }

        $newStatus = ! $user->is_active;

        // If deactivating, protect the last active owner
        if (! $newStatus && $user->isOwner()) {
            $activeOwnerCount = User::whereHas('role', fn ($q) => $q->where('name', 'OWNER'))
                ->where('is_active', true)
                ->count();

            if ($activeOwnerCount <= 1) {
                return ApiResponse::validationError([
                    'status' => ['Cannot deactivate the last active Owner account. At least one active owner must remain.'],
                ], 'Operation prohibited: Last active owner protection.');
            }
        }

        DB::transaction(function () use ($user, $newStatus, $currentUser) {
            $oldStatus = $user->is_active;
            $user->is_active = $newStatus;
            $user->save();

            // When deactivated, immediately invalidate tokens & active sessions
            if (! $newStatus) {
                if (Schema::hasTable('personal_access_tokens')) {
                    $user->tokens()->delete();
                }
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
            }

            AuditService::log(
                $newStatus ? 'STAFF_ACTIVATED' : 'STAFF_DEACTIVATED',
                $user,
                ['is_active' => $oldStatus],
                ['is_active' => $newStatus],
                $currentUser->id
            );
        });

        $actionText = $newStatus ? 'activated' : 'deactivated';

        return ApiResponse::success($user->load('role'), "Staff account has been {$actionText} successfully.");
    }

    /**
     * Display recent administrative audit logs.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $logs = \App\Models\AuditLog::with('user:id,name,email')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::success($logs, 'Audit logs retrieved successfully.');
    }
}
