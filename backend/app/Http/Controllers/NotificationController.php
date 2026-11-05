<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Display a listing of notifications for the current authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $this->notificationService->getUserNotificationsQuery($user);

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $limit = $request->integer('limit', 20);
        $notifications = $query->limit($limit)->get();

        $unreadCount = (clone $this->notificationService->getUserNotificationsQuery($user))
            ->where('is_read', false)
            ->count();

        return ApiResponse::success([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ], 'Notifications retrieved successfully.');
    }

    /**
     * Return count of unread notifications for badge display.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->notificationService->getUserNotificationsQuery($user)
            ->where('is_read', false)
            ->count();

        return ApiResponse::success([
            'unread_count' => $count,
        ], 'Unread notification count retrieved.');
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        // Check if user has permission to view/read this notification
        if (! $user->isOwner() && ! $user->isAdmin()) {
            if ($notification->user_id && $notification->user_id !== $user->id) {
                return ApiResponse::forbidden('You do not have access to this notification.');
            }
            if ($notification->role_id && $notification->role_id !== $user->role_id) {
                return ApiResponse::forbidden('You do not have access to this notification.');
            }
        }

        $notification->update([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ]);

        return ApiResponse::success($notification, 'Notification marked as read.');
    }

    /**
     * Mark all notifications for the current user as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();

        $this->notificationService->getUserNotificationsQuery($user)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => $now,
            ]);

        return ApiResponse::success(null, 'All notifications marked as read.');
    }
}

