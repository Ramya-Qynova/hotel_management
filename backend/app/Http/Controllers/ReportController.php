<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Get Executive Financial Summary and Profitability analysis.
     */
    public function summary(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $data = $this->reportService->getExecutiveSummary($range);

        return ApiResponse::success($data, 'Executive summary compiled.');
    }

    /**
     * Get Room Occupancy, Inventory, and Category Performance.
     */
    public function occupancy(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $data = $this->reportService->getOccupancyReport($range);

        return ApiResponse::success($data, 'Occupancy report compiled.');
    }

    /**
     * Get Booking volume and status distribution.
     */
    public function bookings(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $data = $this->reportService->getBookingReport($range);

        return ApiResponse::success($data, 'Booking report compiled.');
    }

    /**
     * Get Check-in & Check-out arrivals and departures.
     */
    public function checkInOut(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $data = $this->reportService->getCheckInOutReport($range);

        return ApiResponse::success($data, 'Check-in and check-out report compiled.');
    }

    /**
     * Get Payment Collections by method, refunds, and outstanding balances.
     */
    public function payments(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $data = $this->reportService->getPaymentReport($range);

        return ApiResponse::success($data, 'Payment collection report compiled.');
    }

    /**
     * Get Service revenue breakdown.
     */
    public function services(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $data = $this->reportService->getServiceRevenueReport($range);

        return ApiResponse::success($data, 'Service revenue report compiled.');
    }

    /**
     * Get Expense report grouped by category.
     */
    public function expenses(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $data = $this->reportService->getExpenseReport($range);

        return ApiResponse::success($data, 'Expense report compiled.');
    }

    /**
     * Get Cash register reconciliation report.
     */
    public function cashRegister(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $data = $this->reportService->getCashRegisterReport($range);

        return ApiResponse::success($data, 'Cash register report compiled.');
    }

    /**
     * Export reports to CSV.
     */
    public function exportCsv(Request $request): Response
    {
        $reportType = $request->input('type', 'summary');
        $range = $this->resolveRange($request);

        $csv = $this->reportService->generateCsv($reportType, $range);
        $filename = "report-{$reportType}-{$range['start_date']}-to-{$range['end_date']}.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Helper to parse request parameters into normalized date range.
     */
    protected function resolveRange(Request $request): array
    {
        return $this->reportService->resolveDateRange(
            $request->input('range_type'),
            $request->input('from_date'),
            $request->input('to_date')
        );
    }
}

