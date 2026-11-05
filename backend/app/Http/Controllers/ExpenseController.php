<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\ExpenseAndRegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseAndRegisterService $expenseManager
    ) {}

    /**
     * Display a listing of expense categories.
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = $this->expenseManager->getCategories($request->boolean('active_only', false));

        return ApiResponse::success($categories, 'Expense categories retrieved.');
    }

    /**
     * Store a newly created expense category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:expense_categories,name',
            'code' => 'nullable|string|max:50|unique:expense_categories,code',
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $category = $this->expenseManager->createCategory($validated);

        return ApiResponse::success($category, 'Expense category created successfully.', 201);
    }

    /**
     * Display a listing of recorded expenses with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['expense_category_id', 'payment_method', 'date_from', 'date_to', 'search']);
        $expenses = $this->expenseManager->getExpenses($filters)->paginate($request->integer('per_page', 20));

        return ApiResponse::success($expenses, 'Expenses retrieved successfully.');
    }

    /**
     * Store a newly recorded expense.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'title' => 'required|string|max:150',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', Rule::in(['CASH', 'UPI', 'CARD', 'BANK_TRANSFER'])],
            'expense_date' => 'nullable|date_format:Y-m-d',
            'paid_to' => 'required|string|max:150',
            'reference_no' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $expense = $this->expenseManager->recordExpense($validated, $request->user());
            return ApiResponse::success($expense, 'Expense recorded successfully.', 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
