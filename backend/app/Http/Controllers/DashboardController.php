<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\BookingService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected BookingService $bookingService
    ) {}

    /**
     * Get aggregated real-time dashboard data for the frontend.
     */
    public function index(Request $request): JsonResponse
    {
        // Automatically expire any outdated pending payment holds before compiling stats
        $this->bookingService->expirePendingHolds();

        $days = $request->integer('days', 7);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 7;
        }

        $data = $this->dashboardService->getDashboardData($days);

        return ApiResponse::success($data, 'Dashboard metrics compiled successfully.');
    }
}

