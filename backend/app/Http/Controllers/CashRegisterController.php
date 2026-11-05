<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\ExpenseAndRegisterService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function __construct(
        protected ExpenseAndRegisterService $registerService
    ) {}

    /**
     * Get cash register summary and transaction history for a given date.
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        $summary = $this->registerService->getDailyRegisterSummary($date);

        return ApiResponse::success($summary, 'Cash register summary compiled.');
    }

    /**
     * Record opening cash float for the day.
     */
    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'amount' => 'required|numeric|min:0.00',
            'notes' => 'nullable|string|max:255',
        ]);

        $date = $validated['date'] ?? Carbon::today()->format('Y-m-d');

        try {
            $transaction = $this->registerService->recordOpeningCash(
                $date,
                (float) $validated['amount'],
                $request->user(),
                $validated['notes'] ?? null
            );

            return ApiResponse::success($transaction, 'Opening cash float established.', 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Reconcile and close cash register for the day.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'actual_cash' => 'required|numeric|min:0.00',
            'notes' => 'nullable|string|max:255',
        ]);

        $date = $validated['date'] ?? Carbon::today()->format('Y-m-d');

        try {
            $closing = $this->registerService->reconcileAndClose(
                $date,
                (float) $validated['actual_cash'],
                $request->user(),
                $validated['notes'] ?? null
            );

            return ApiResponse::success($closing, 'Daily cash register closed and audited successfully.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
