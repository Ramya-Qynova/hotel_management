<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingService;
use App\Models\CashRegisterTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HotelSetting;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Parse date range inputs into Carbon instances and formatting label.
     */
    public function resolveDateRange(
        ?string $rangeType = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $now = Carbon::now();

        switch (strtolower((string) $rangeType)) {
            case 'today':
            case 'daily':
                $start = Carbon::today()->startOfDay();
                $end = Carbon::today()->endOfDay();
                $label = 'Today (' . $start->format('d M, Y') . ')';
                break;

            case '7d':
            case 'weekly':
                $start = Carbon::today()->subDays(6)->startOfDay();
                $end = Carbon::today()->endOfDay();
                $label = 'Last 7 Days (' . $start->format('d M') . ' - ' . $end->format('d M, Y') . ')';
                break;

            case 'this_month':
                $start = Carbon::now()->startOfMonth()->startOfDay();
                $end = Carbon::now()->endOfMonth()->endOfDay();
                $label = 'This Month (' . $start->format('F Y') . ')';
                break;

            case 'custom':
                $start = $fromDate ? Carbon::parse($fromDate)->startOfDay() : Carbon::today()->subDays(29)->startOfDay();
                $end = $toDate ? Carbon::parse($toDate)->endOfDay() : Carbon::today()->endOfDay();
                $label = 'Custom (' . $start->format('d M, Y') . ' - ' . $end->format('d M, Y') . ')';
                break;

            case '30d':
            case 'monthly':
            default:
                $start = Carbon::today()->subDays(29)->startOfDay();
                $end = Carbon::today()->endOfDay();
                $label = 'Last 30 Days (' . $start->format('d M') . ' - ' . $end->format('d M, Y') . ')';
                break;
        }

        if ($start->gt($end)) {
            $temp = $start;
            $start = $end->copy()->startOfDay();
            $end = $temp->copy()->endOfDay();
        }

        $days = max(1, $start->diffInDays($end) + 1);

        return [
            'start' => $start,
            'end' => $end,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'label' => $label,
            'days' => (int) $days,
        ];
    }

    /**
     * Compile complete Executive Financial Summary report.
     */
    public function getExecutiveSummary(array $range): array
    {
        $startDate = $range['start'];
        $endDate = $range['end'];
        $startStr = $range['start_date'];
        $endStr = $range['end_date'];

        // 1. Total Booking Value (non-cancelled bookings created in range)
        $totalBookingValue = (float) Booking::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'CANCELLED')
            ->sum('grand_total');

        // 2. Room Revenue & Service Revenue (from bookings in range)
        $roomRevenue = (float) Booking::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'CANCELLED')
            ->sum('base_room_charges');

        $taxRevenue = (float) Booking::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'CANCELLED')
            ->sum('total_tax_amount');

        $serviceRevenue = (float) BookingService::whereHas('booking', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', '!=', 'CANCELLED');
        })->sum('subtotal');

        // 3. Collections (Actual cash/online payments received in date range)
        $amountCollected = (float) Payment::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['SUCCESS', 'COMPLETED'])
            ->sum('amount');

        // 4. Refunds (Actual refunds processed in date range)
        $refunds = (float) Refund::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'PROCESSED')
            ->sum('amount');

        // 5. Net Collections
        $netCollections = max(0.00, round($amountCollected - $refunds, 2));

        // 6. Outstanding Balance on bookings in range
        $activeBookings = Booking::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT'])
            ->with(['payments' => function ($q) {
                $q->whereIn('status', ['SUCCESS', 'COMPLETED']);
            }])
            ->get();

        $outstandingAmount = 0.00;
        foreach ($activeBookings as $b) {
            $paid = (float) $b->payments->sum('amount');
            $due = max(0.00, round((float) $b->grand_total - $paid, 2));
            $outstandingAmount += $due;
        }

        // 7. Expenses in date range
        $expenses = (float) Expense::whereBetween('expense_date', [$startStr, $endStr])
            ->sum('amount');

        // 8. Profit Summaries:
        // Cash Basis = Net Collections - Expenses
        $cashBasisProfit = round($netCollections - $expenses, 2);

        // Accrual Basis = (Room Revenue + Service Revenue + Tax) - Expenses
        $earnedRevenue = round($roomRevenue + $serviceRevenue + $taxRevenue, 2);
        $accrualBasisProfit = round($earnedRevenue - $expenses, 2);

        // Daily Trend for the period
        $dailyTrend = $this->buildDailyFinancialTrend($range);

        return [
            'period' => [
                'start_date' => $startStr,
                'end_date' => $endStr,
                'label' => $range['label'],
                'days' => $range['days'],
            ],
            'financials' => [
                'total_booking_value' => $totalBookingValue,
                'room_revenue' => $roomRevenue,
                'service_revenue' => $serviceRevenue,
                'tax_collected' => $taxRevenue,
                'earned_revenue' => $earnedRevenue,
                'amount_collected' => $amountCollected,
                'refunds' => $refunds,
                'net_collections' => $netCollections,
                'outstanding_amount' => round($outstandingAmount, 2),
                'expenses' => $expenses,
                'cash_profit' => $cashBasisProfit,
                'accrual_profit' => $accrualBasisProfit,
            ],
            'profit_explanation' => [
                'cash_basis' => [
                    'label' => 'Cash Basis Net Profit',
                    'formula' => 'Net Collections (₹' . number_format($netCollections, 2) . ') - Expenses (₹' . number_format($expenses, 2) . ')',
                    'value' => $cashBasisProfit,
                    'description' => 'Realized cash flow reflecting actual payments received minus operating expenses disbursed.',
                ],
                'accrual_basis' => [
                    'label' => 'Accrual Basis Net Profit',
                    'formula' => 'Total Invoiced/Billed Revenue (₹' . number_format($earnedRevenue, 2) . ') - Expenses (₹' . number_format($expenses, 2) . ')',
                    'value' => $accrualBasisProfit,
                    'description' => 'Accounting profit reflecting all room stays and services booked in the period regardless of collection timing.',
                ],
            ],
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * Compile Room Occupancy & Inventory Performance report.
     */
    public function getOccupancyReport(array $range): array
    {
        $startStr = $range['start_date'];
        $endStr = $range['end_date'];
        $days = $range['days'];

        $totalRooms = Room::where('is_active', true)->count();
        $totalRoomNightsAvailable = $totalRooms * $days;

        // Occupied room nights in period:
        // An active booking room occupies a night for every date between check_in_date and check_out_date that falls within [start, end]
        $bookingRooms = BookingRoom::whereHas('booking', function ($q) {
            $q->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT']);
        })
            ->where('check_in_date', '<', $endStr)
            ->where('check_out_date', '>', $startStr)
            ->with(['room.roomType', 'booking'])
            ->get();

        $occupiedRoomNights = 0;
        $categoryStats = [];

        // Pre-initialize room types
        $allRoomTypes = RoomType::where('is_active', true)->withCount('rooms')->get();
        foreach ($allRoomTypes as $rt) {
            $categoryStats[$rt->id] = [
                'room_type_id' => $rt->id,
                'name' => $rt->name,
                'total_rooms' => $rt->rooms_count,
                'nights_available' => $rt->rooms_count * $days,
                'nights_occupied' => 0,
                'revenue' => 0.00,
                'occupancy_rate' => 0.0,
                'adr' => 0.00,
            ];
        }

        foreach ($bookingRooms as $br) {
            $ci = Carbon::parse($br->check_in_date);
            $co = Carbon::parse($br->check_out_date);

            // Clamp overlap to report start and end
            $overlapStart = $ci->lt($range['start']) ? $range['start']->copy() : $ci;
            $overlapEnd = $co->gt($range['end']) ? $range['end']->copy() : $co;

            $nights = max(0, $overlapStart->diffInDays($overlapEnd));
            $occupiedRoomNights += $nights;

            $rtId = $br->room?->room_type_id;
            if ($rtId && isset($categoryStats[$rtId])) {
                $categoryStats[$rtId]['nights_occupied'] += $nights;
                $categoryStats[$rtId]['revenue'] += (float) $br->subtotal;
            }
        }

        // Calculate rates per category
        foreach ($categoryStats as &$stat) {
            $stat['occupancy_rate'] = $stat['nights_available'] > 0
                ? round(($stat['nights_occupied'] / $stat['nights_available']) * 100, 1)
                : 0.0;
            $stat['adr'] = $stat['nights_occupied'] > 0
                ? round($stat['revenue'] / $stat['nights_occupied'], 2)
                : 0.00;
        }
        unset($stat);

        $occupancyRate = $totalRoomNightsAvailable > 0
            ? round(($occupiedRoomNights / $totalRoomNightsAvailable) * 100, 1)
            : 0.0;

        $roomRevenue = (float) Booking::whereBetween('created_at', [$range['start'], $range['end']])
            ->where('status', '!=', 'CANCELLED')
            ->sum('base_room_charges');

        $adr = $occupiedRoomNights > 0 ? round($roomRevenue / $occupiedRoomNights, 2) : 0.00;
        $revpar = $totalRoomNightsAvailable > 0 ? round($roomRevenue / $totalRoomNightsAvailable, 2) : 0.00;

        return [
            'period' => $range['label'],
            'total_rooms' => $totalRooms,
            'days' => $days,
            'total_nights_available' => $totalRoomNightsAvailable,
            'total_nights_occupied' => $occupiedRoomNights,
            'occupancy_rate_percent' => $occupancyRate,
            'average_daily_rate' => $adr,
            'revenue_per_available_room' => $revpar,
            'room_categories' => array_values($categoryStats),
        ];
    }

    /**
     * Compile Booking Status and Volume report.
     */
    public function getBookingReport(array $range): array
    {
        $startDate = $range['start'];
        $endDate = $range['end'];

        $bookings = Booking::whereBetween('created_at', [$startDate, $endDate])
            ->with(['guest', 'bookingRooms.room.roomType'])
            ->orderByDesc('id')
            ->get();

        $statusCounts = [
            'CONFIRMED' => 0,
            'CHECKED_IN' => 0,
            'CHECKED_OUT' => 0,
            'CANCELLED' => 0,
            'PENDING_PAYMENT' => 0,
            'NO_SHOW' => 0,
        ];

        $totalGrandTotal = 0.00;
        $totalNights = 0;
        $nonCancelledCount = 0;

        foreach ($bookings as $b) {
            $status = $b->status;
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            } else {
                $statusCounts[$status] = 1;
            }

            if ($status !== 'CANCELLED') {
                $totalGrandTotal += (float) $b->grand_total;
                $nonCancelledCount++;
                if ($b->check_in_date && $b->check_out_date) {
                    $totalNights += max(1, $b->check_in_date->diffInDays($b->check_out_date));
                }
            }
        }

        $totalCount = $bookings->count();
        $cancellationRate = $totalCount > 0 ? round(($statusCounts['CANCELLED'] / $totalCount) * 100, 1) : 0.0;
        $avgBookingValue = $nonCancelledCount > 0 ? round($totalGrandTotal / $nonCancelledCount, 2) : 0.00;
        $avgStayLength = $nonCancelledCount > 0 ? round($totalNights / $nonCancelledCount, 1) : 0.0;

        return [
            'period' => $range['label'],
            'total_bookings' => $totalCount,
            'status_distribution' => $statusCounts,
            'cancellation_rate' => $cancellationRate,
            'average_booking_value' => $avgBookingValue,
            'average_stay_length_nights' => $avgStayLength,
            'bookings' => $bookings->take(50)->map(function ($b) {
                return [
                    'id' => $b->id,
                    'booking_number' => $b->booking_number,
                    'guest_name' => $b->guest ? "{$b->guest->first_name} {$b->guest->last_name}" : 'Guest',
                    'check_in' => $b->check_in_date?->format('Y-m-d'),
                    'check_out' => $b->check_out_date?->format('Y-m-d'),
                    'status' => $b->status,
                    'grand_total' => (float) $b->grand_total,
                    'created_at' => $b->created_at?->format('Y-m-d H:i'),
                ];
            }),
        ];
    }

    /**
     * Compile Check-in & Check-out Arrivals / Departures report.
     */
    public function getCheckInOutReport(array $range): array
    {
        $startStr = $range['start_date'];
        $endStr = $range['end_date'];

        $arrivals = Booking::whereBetween('check_in_date', [$startStr, $endStr])
            ->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT'])
            ->with(['guest', 'bookingRooms.room.roomType'])
            ->orderBy('check_in_date')
            ->get();

        $departures = Booking::whereBetween('check_out_date', [$startStr, $endStr])
            ->whereIn('status', ['CHECKED_IN', 'CHECKED_OUT'])
            ->with(['guest', 'bookingRooms.room.roomType'])
            ->orderBy('check_out_date')
            ->get();

        return [
            'period' => $range['label'],
            'total_arrivals' => $arrivals->count(),
            'total_departures' => $departures->count(),
            'checked_in_count' => $arrivals->where('status', 'CHECKED_IN')->count(),
            'completed_check_outs_count' => $departures->where('status', 'CHECKED_OUT')->count(),
            'arrivals' => $arrivals->map(fn ($b) => [
                'id' => $b->id,
                'booking_number' => $b->booking_number,
                'guest_name' => $b->guest ? "{$b->guest->first_name} {$b->guest->last_name}" : 'Guest',
                'phone' => $b->guest?->phone,
                'check_in_date' => $b->check_in_date?->format('Y-m-d'),
                'status' => $b->status,
                'rooms' => $b->bookingRooms->map(fn ($br) => $br->room?->room_number)->filter()->implode(', '),
            ]),
            'departures' => $departures->map(fn ($b) => [
                'id' => $b->id,
                'booking_number' => $b->booking_number,
                'guest_name' => $b->guest ? "{$b->guest->first_name} {$b->guest->last_name}" : 'Guest',
                'phone' => $b->guest?->phone,
                'check_out_date' => $b->check_out_date?->format('Y-m-d'),
                'status' => $b->status,
                'rooms' => $b->bookingRooms->map(fn ($br) => $br->room?->room_number)->filter()->implode(', '),
            ]),
        ];
    }

    /**
     * Compile Payment Collections by Method report.
     */
    public function getPaymentReport(array $range): array
    {
        $startDate = $range['start'];
        $endDate = $range['end'];

        $payments = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['SUCCESS', 'COMPLETED'])
            ->with(['booking.guest', 'receivedBy'])
            ->orderByDesc('id')
            ->get();

        $byMethod = [];
        $totalCollected = 0.00;

        foreach ($payments as $p) {
            $method = strtoupper($p->payment_method ?: 'OTHER');
            if (! isset($byMethod[$method])) {
                $byMethod[$method] = [
                    'method' => $method,
                    'count' => 0,
                    'amount' => 0.00,
                ];
            }
            $byMethod[$method]['count']++;
            $byMethod[$method]['amount'] += (float) $p->amount;
            $totalCollected += (float) $p->amount;
        }

        // Outstanding bookings in range
        $outstandingBookings = Booking::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT'])
            ->with(['guest', 'payments' => fn ($q) => $q->whereIn('status', ['SUCCESS', 'COMPLETED'])])
            ->get()
            ->map(function ($b) {
                $paid = (float) $b->payments->sum('amount');
                $due = max(0.00, round((float) $b->grand_total - $paid, 2));
                return [
                    'id' => $b->id,
                    'booking_number' => $b->booking_number,
                    'guest_name' => $b->guest ? "{$b->guest->first_name} {$b->guest->last_name}" : 'Guest',
                    'phone' => $b->guest?->phone,
                    'grand_total' => (float) $b->grand_total,
                    'paid_amount' => $paid,
                    'balance_due' => $due,
                    'status' => $b->status,
                ];
            })
            ->filter(fn ($item) => $item['balance_due'] > 0.00)
            ->values();

        // Refunds in range
        $refunds = Refund::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'PROCESSED')
            ->with(['booking.guest', 'processedBy'])
            ->orderByDesc('id')
            ->get();

        $totalRefunded = (float) $refunds->sum('amount');

        return [
            'period' => $range['label'],
            'total_collected' => round($totalCollected, 2),
            'total_refunded' => round($totalRefunded, 2),
            'net_collected' => max(0.00, round($totalCollected - $totalRefunded, 2)),
            'payment_methods' => array_values($byMethod),
            'outstanding_count' => $outstandingBookings->count(),
            'outstanding_total' => round($outstandingBookings->sum('balance_due'), 2),
            'outstanding_bookings' => $outstandingBookings->take(30),
            'refunds' => $refunds->map(fn ($r) => [
                'id' => $r->id,
                'refund_reference' => $r->refund_reference,
                'booking_number' => $r->booking?->booking_number,
                'guest_name' => $r->booking?->guest ? "{$r->booking->guest->first_name} {$r->booking->guest->last_name}" : 'Guest',
                'amount' => (float) $r->amount,
                'refund_method' => $r->refund_method,
                'reason' => $r->reason,
                'processed_by' => $r->processedBy?->name,
                'date' => $r->created_at?->format('Y-m-d H:i'),
            ]),
        ];
    }

    /**
     * Compile Service Revenue report.
     */
    public function getServiceRevenueReport(array $range): array
    {
        $startDate = $range['start'];
        $endDate = $range['end'];

        $bookingServices = BookingService::whereHas('booking', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', '!=', 'CANCELLED');
        })
            ->with('service')
            ->get();

        $serviceStats = [];
        $totalServiceRevenue = 0.00;

        foreach ($bookingServices as $bs) {
            $sId = $bs->service_id;
            $name = $bs->service?->name ?? 'Custom Service';
            $cat = $bs->service?->category ?? 'GENERAL';
            $qty = (int) $bs->quantity;
            $rev = round((float) ($bs->subtotal ?? ($bs->unit_price * $qty)), 2);

            if (! isset($serviceStats[$sId])) {
                $serviceStats[$sId] = [
                    'service_id' => $sId,
                    'name' => $name,
                    'category' => $cat,
                    'units_sold' => 0,
                    'total_revenue' => 0.00,
                ];
            }

            $serviceStats[$sId]['units_sold'] += $qty;
            $serviceStats[$sId]['total_revenue'] += $rev;
            $totalServiceRevenue += $rev;
        }

        return [
            'period' => $range['label'],
            'total_service_revenue' => round($totalServiceRevenue, 2),
            'services' => array_values($serviceStats),
        ];
    }

    /**
     * Compile Expense breakdown report by category.
     */
    public function getExpenseReport(array $range): array
    {
        $startStr = $range['start_date'];
        $endStr = $range['end_date'];

        $expenses = Expense::whereBetween('expense_date', [$startStr, $endStr])
            ->with(['category', 'recordedBy'])
            ->orderByDesc('expense_date')
            ->get();

        $categoryGroups = [];
        $totalExpense = 0.00;

        foreach ($expenses as $e) {
            $catId = $e->expense_category_id;
            $catName = $e->category?->name ?? 'Uncategorized';
            $amt = (float) $e->amount;

            if (! isset($categoryGroups[$catId])) {
                $categoryGroups[$catId] = [
                    'category_id' => $catId,
                    'name' => $catName,
                    'count' => 0,
                    'total_amount' => 0.00,
                ];
            }

            $categoryGroups[$catId]['count']++;
            $categoryGroups[$catId]['total_amount'] += $amt;
            $totalExpense += $amt;
        }

        return [
            'period' => $range['label'],
            'total_expenses' => round($totalExpense, 2),
            'by_category' => array_values($categoryGroups),
            'expenses' => $expenses->take(50)->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'category' => $e->category?->name ?? 'Uncategorized',
                'amount' => (float) $e->amount,
                'expense_date' => $e->expense_date?->format('Y-m-d'),
                'payment_method' => $e->payment_method,
                'recorded_by' => $e->recordedBy?->name,
            ]),
        ];
    }

    /**
     * Compile Cash Register Reconciliation report.
     */
    public function getCashRegisterReport(array $range): array
    {
        $startStr = $range['start_date'];
        $endStr = $range['end_date'];

        $transactions = CashRegisterTransaction::whereBetween('register_date', [$startStr, $endStr])
            ->with(['performedBy', 'payment', 'refund'])
            ->orderByDesc('id')
            ->get();

        $totalCashIn = (float) $transactions->where('transaction_type', 'CASH_IN')->sum('amount');
        $totalCashOut = (float) $transactions->where('transaction_type', 'CASH_OUT')->sum('amount');

        return [
            'period' => $range['label'],
            'total_cash_in' => round($totalCashIn, 2),
            'total_cash_out' => round($totalCashOut, 2),
            'net_cash_flow' => round($totalCashIn - $totalCashOut, 2),
            'transactions_count' => $transactions->count(),
            'transactions' => $transactions->take(50)->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->register_date?->format('Y-m-d'),
                'type' => $t->transaction_type,
                'category' => $t->category,
                'amount' => (float) $t->amount,
                'balance_after' => (float) $t->balance_after,
                'notes' => $t->notes,
                'performed_by' => $t->performedBy?->name,
                'created_at' => $t->created_at?->format('Y-m-d H:i'),
            ]),
        ];
    }

    /**
     * Generate CSV content string for any report section.
     */
    public function generateCsv(string $reportType, array $range): string
    {
        $output = fopen('php://temp', 'r+');
        $currCode = HotelSetting::getValue('currency_code', 'INR');

        switch ($reportType) {
            case 'financials':
            case 'revenue':
            case 'summary':
                $summary = $this->getExecutiveSummary($range);
                fputcsv($output, ['Metric', "Amount ({$currCode})", 'Notes']);
                fputcsv($output, ['Total Booking Value', $summary['financials']['total_booking_value'], 'Gross booking volume']);
                fputcsv($output, ['Room Revenue', $summary['financials']['room_revenue'], 'Room base tariff']);
                fputcsv($output, ['Service Revenue', $summary['financials']['service_revenue'], 'Ancillary services']);
                fputcsv($output, ['Tax Collected', $summary['financials']['tax_collected'], 'GST / Taxes']);
                fputcsv($output, ['Total Collections', $summary['financials']['amount_collected'], 'Payments collected']);
                fputcsv($output, ['Refunds', $summary['financials']['refunds'], 'Processed refunds']);
                fputcsv($output, ['Net Collections', $summary['financials']['net_collections'], 'Collections minus refunds']);
                fputcsv($output, ['Operating Expenses', $summary['financials']['expenses'], 'Total expenses']);
                fputcsv($output, ['Cash Basis Net Profit', $summary['financials']['cash_profit'], 'Net Collections minus Expenses']);
                fputcsv($output, ['Accrual Basis Net Profit', $summary['financials']['accrual_profit'], 'Total Earned Revenue minus Expenses']);
                break;

            case 'bookings':
                $report = $this->getBookingReport($range);
                fputcsv($output, ['Booking Number', 'Guest Name', 'Check-In', 'Check-Out', 'Status', "Grand Total ({$currCode})", 'Created At']);
                foreach ($report['bookings'] as $b) {
                    fputcsv($output, [$b['booking_number'], $b['guest_name'], $b['check_in'], $b['check_out'], $b['status'], $b['grand_total'], $b['created_at']]);
                }
                break;

            case 'payments':
                $report = $this->getPaymentReport($range);
                fputcsv($output, ['Payment Method', 'Transaction Count', "Total Amount ({$currCode})"]);
                foreach ($report['payment_methods'] as $pm) {
                    fputcsv($output, [$pm['method'], $pm['count'], $pm['amount']]);
                }
                fputcsv($output, []);
                fputcsv($output, ['Refund Reference', 'Booking Number', 'Guest', "Amount ({$currCode})", 'Method', 'Reason', 'Date']);
                foreach ($report['refunds'] as $r) {
                    fputcsv($output, [$r['refund_reference'], $r['booking_number'], $r['guest_name'], $r['amount'], $r['refund_method'], $r['reason'], $r['date']]);
                }
                break;

            case 'expenses':
                $report = $this->getExpenseReport($range);
                fputcsv($output, ['Date', 'Title', 'Category', "Amount ({$currCode})", 'Payment Method', 'Recorded By']);
                foreach ($report['expenses'] as $e) {
                    fputcsv($output, [$e['expense_date'], $e['title'], $e['category'], $e['amount'], $e['payment_method'], $e['recorded_by']]);
                }
                break;

            default:
                fputcsv($output, ['Report Type', $reportType, 'Period', $range['label']]);
                break;
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Build daily revenue and expense trend for charts in reports.
     */
    protected function buildDailyFinancialTrend(array $range): array
    {
        $start = $range['start']->copy();
        $days = min($range['days'], 60); // Cap trend points to 60 for clean visualization

        $dailyRevenues = Payment::selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->whereIn('status', ['SUCCESS', 'COMPLETED'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'date')
            ->toArray();

        $dailyExpenses = Expense::selectRaw('expense_date as date, SUM(amount) as total')
            ->whereBetween('expense_date', [$range['start_date'], $range['end_date']])
            ->groupBy('expense_date')
            ->pluck('total', 'date')
            ->toArray();

        $trend = [];
        $intervalDays = max(1, (int) round($range['days'] / $days));

        for ($i = 0; $i < $range['days']; $i += $intervalDays) {
            $cur = $start->copy()->addDays($i);
            $dKey = $cur->format('Y-m-d');
            $rev = isset($dailyRevenues[$dKey]) ? (float) $dailyRevenues[$dKey] : 0.00;
            $exp = isset($dailyExpenses[$dKey]) ? (float) $dailyExpenses[$dKey] : 0.00;

            $trend[] = [
                'date' => $dKey,
                'label' => $cur->format('d M'),
                'collections' => $rev,
                'expenses' => $exp,
                'net' => round($rev - $exp, 2),
            ];
        }

        return $trend;
    }
}
