<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized('Authentication is required to access this resource.');
        }

        if (! $user->is_active) {
            return ApiResponse::forbidden('Your account has been deactivated. Please contact an administrator.');
        }

        if (empty($roles)) {
            return $next($request);
        }

        // Flatten roles in case comma-separated strings were passed in route definitions (e.g. role:OWNER,ADMIN)
        $flattenedRoles = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $part) {
                $trimmed = trim($part);
                if ($trimmed !== '') {
                    $flattenedRoles[] = $trimmed;
                }
            }
        }

        if (! $user->hasRole($flattenedRoles)) {
            $allowedRoles = implode(', ', $flattenedRoles);
            return ApiResponse::forbidden("Forbidden. This action requires one of the following roles: {$allowedRoles}.");
        }

        return $next($request);
    }
}

