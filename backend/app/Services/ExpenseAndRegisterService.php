<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CashRegisterTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExpenseAndRegisterService
{
    // -------------------------------------------------------------
    // Expense Categories
    // -------------------------------------------------------------

    public function getCategories(bool $activeOnly = false): Collection
    {
        $query = ExpenseCategory::query()->withCount('expenses');
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        return $query->orderBy('name')->get();
    }

    public function createCategory(array $data): ExpenseCategory
    {
        $name = trim($data['name']);
        $code = strtoupper(trim($data['code'] ?? Str::slug($name)));

        return ExpenseCategory::create([
            'name' => $name,
            'code' => $code,
            'description' => $data['description'] ?? null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }

    // -------------------------------------------------------------
    // Expenses
    // -------------------------------------------------------------

    public function getExpenses(array $filters = [])
    {
        $query = Expense::query()->with(['category', 'recordedBy']);

        if (!empty($filters['expense_category_id'])) {
            $query->where('expense_category_id', $filters['expense_category_id']);
        }
        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', strtoupper($filters['payment_method']));
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('expense_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('expense_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('paid_to', 'like', "%{$s}%")
                  ->orWhere('reference_no', 'like', "%{$s}%");
            });
        }

        return $query->orderByDesc('expense_date')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function recordExpense(array $data, User $user): Expense
    {
        $amount = (float) $data['amount'];
        if ($amount <= 0.00) {
            throw new InvalidArgumentException('Expense amount must be greater than zero.');
        }

        $method = strtoupper($data['payment_method'] ?? 'CASH');
        if (!in_array($method, ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER'], true)) {
            $method = 'CASH';
        }

        $date = $data['expense_date'] ?? Carbon::today()->format('Y-m-d');

        return DB::transaction(function () use ($data, $amount, $method, $date, $user) {
            $expense = Expense::create([
                'expense_category_id' => $data['expense_category_id'],
                'title' => trim($data['title']),
                'amount' => $amount,
                'payment_method' => $method,
                'expense_date' => $date,
                'paid_to' => trim($data['paid_to']),
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'] ?? null,
                'recorded_by' => $user->id,
            ]);

            // If cash expense, record physical cash transaction
            if ($method === 'CASH') {
                $lastBalance = (float) (CashRegisterTransaction::where('register_date', $date)
                    ->latest('id')
                    ->value('balance_after') ?? 0.00);

                $balanceAfter = max(0.00, round($lastBalance - $amount, 2));

                CashRegisterTransaction::create([
                    'register_date' => $date,
                    'transaction_type' => 'CASH_OUT',
                    'category' => 'EXPENSE',
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'expense_id' => $expense->id,
                    'performed_by' => $user->id,
                    'notes' => "Cash expense: {$expense->title} (Paid to: {$expense->paid_to})",
                ]);
            }

            return $expense->load(['category', 'recordedBy']);
        });
    }

    // -------------------------------------------------------------
    // Cash Register & Daily Financial Reconciliation
    // -------------------------------------------------------------

    /**
     * Get cash register summary and physical cash movements for a specific date.
     */
    public function getDailyRegisterSummary(string $date): array
    {
        $previousDate = Carbon::parse($date)->subDay()->format('Y-m-d');

        // Opening balance: either explicit OPENING_BALANCE transaction today or previous day's latest balance
        $explicitOpening = CashRegisterTransaction::where('register_date', $date)
            ->where('transaction_type', 'OPENING_BALANCE')
            ->first();

        $openingBalance = $explicitOpening
            ? (float) $explicitOpening->amount
            : (float) (CashRegisterTransaction::where('register_date', $previousDate)
                ->latest('id')
                ->value('balance_after') ?? 0.00);

        // Sum Cash In (Payments)
        $cashIn = (float) CashRegisterTransaction::where('register_date', $date)
            ->where('transaction_type', 'CASH_IN')
            ->sum('amount');

        // Sum Cash Out (Refunds and Expenses)
        $cashRefunds = (float) CashRegisterTransaction::where('register_date', $date)
            ->where('transaction_type', 'CASH_OUT')
            ->where('category', 'REFUND')
            ->sum('amount');

        $cashExpenses = (float) CashRegisterTransaction::where('register_date', $date)
            ->where('transaction_type', 'CASH_OUT')
            ->where('category', 'EXPENSE')
            ->sum('amount');

        $totalCashOut = round($cashRefunds + $cashExpenses, 2);

        $expectedClosing = round($openingBalance + $cashIn - $totalCashOut, 2);

        // Check if day was closed
        $closingAudit = CashRegisterTransaction::where('register_date', $date)
            ->where('transaction_type', 'CLOSING_BALANCE')
            ->latest('id')
            ->first();

        $actualClosing = $closingAudit ? (float) $closingAudit->amount : null;
        $variance = $closingAudit ? round($actualClosing - $expectedClosing, 2) : null;

        // Daily financial collections breakdown across all channels
        $allPaymentsToday = Payment::whereDate('paid_at', $date)
            ->where('status', 'SUCCESS')
            ->get();

        $totalCollections = (float) $allPaymentsToday->sum('amount');
        $collectionsByMethod = [
            'CASH' => (float) $allPaymentsToday->where('payment_method', 'CASH')->sum('amount'),
            'UPI' => (float) $allPaymentsToday->where('payment_method', 'UPI')->sum('amount'),
            'CARD' => (float) $allPaymentsToday->where('payment_method', 'CARD')->sum('amount'),
            'ONLINE_GATEWAY' => (float) $allPaymentsToday->where('payment_method', 'ONLINE_GATEWAY')->sum('amount'),
            'BANK_TRANSFER' => (float) $allPaymentsToday->where('payment_method', 'BANK_TRANSFER')->sum('amount'),
        ];

        $allRefundsToday = Refund::whereDate('processed_at', $date)
            ->where('status', 'PROCESSED')
            ->get();

        $totalRefundsAll = (float) $allRefundsToday->sum('amount');
        $netCollectionsAll = round($totalCollections - $totalRefundsAll, 2);

        // Transactions list for audit
        $transactions = CashRegisterTransaction::where('register_date', $date)
            ->with(['payment.booking.guest', 'refund.booking.guest', 'expense.category', 'performedBy'])
            ->orderBy('id')
            ->get();

        return [
            'register_date' => $date,
            'is_closed' => (bool) $closingAudit,
            'closed_at' => $closingAudit?->created_at?->toIso8601String(),
            'closed_by' => $closingAudit?->performedBy?->name,
            'opening_balance' => $openingBalance,
            'cash_in' => $cashIn,
            'cash_refunds' => $cashRefunds,
            'cash_expenses' => $cashExpenses,
            'total_cash_out' => $totalCashOut,
            'expected_closing_balance' => $expectedClosing,
            'actual_closing_balance' => $actualClosing,
            'variance' => $variance,
            'reconciliation' => [
                'total_collections_all_methods' => $totalCollections,
                'total_refunds_all_methods' => $totalRefundsAll,
                'net_collections' => $netCollectionsAll,
                'breakdown' => $collectionsByMethod,
            ],
            'transactions' => $transactions,
        ];
    }

    /**
     * Record opening cash for the day.
     */
    public function recordOpeningCash(string $date, float $amount, User $user, ?string $notes = null): CashRegisterTransaction
    {
        $existing = CashRegisterTransaction::where('register_date', $date)
            ->where('transaction_type', 'OPENING_BALANCE')
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException("Opening balance for {$date} is already recorded.");
        }

        return CashRegisterTransaction::create([
            'register_date' => $date,
            'transaction_type' => 'OPENING_BALANCE',
            'category' => 'OPENING',
            'amount' => $amount,
            'balance_after' => $amount,
            'performed_by' => $user->id,
            'notes' => $notes ?? 'Opening cash float established',
        ]);
    }

    /**
     * Reconcile and close the cash register for the day.
     */
    public function reconcileAndClose(string $date, float $actualCash, User $user, ?string $notes = null): CashRegisterTransaction
    {
        $existing = CashRegisterTransaction::where('register_date', $date)
            ->where('transaction_type', 'CLOSING_BALANCE')
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException("Register for {$date} has already been closed and audited.");
        }

        $summary = $this->getDailyRegisterSummary($date);
        $expected = $summary['expected_closing_balance'];
        $variance = round($actualCash - $expected, 2);

        $auditNote = "Daily close. Actual: ₹{$actualCash}, Expected: ₹{$expected}, Variance: ₹{$variance}";
        if ($notes) {
            $auditNote .= " | Notes: {$notes}";
        }

        return CashRegisterTransaction::create([
            'register_date' => $date,
            'transaction_type' => 'CLOSING_BALANCE',
            'category' => 'CLOSING_AUDIT',
            'amount' => $actualCash,
            'balance_after' => $actualCash,
            'performed_by' => $user->id,
            'notes' => $auditNote,
        ]);
    }
}
