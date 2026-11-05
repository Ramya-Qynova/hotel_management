<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expense extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'expenses';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'expense_category_id',
        'title',
        'amount',
        'payment_method',
        'expense_date',
        'paid_to',
        'reference_no',
        'receipt_image_path',
        'description',
        'recorded_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    /**
     * Get the category for this expense.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * Alias for expense category relationship.
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->category();
    }

    /**
     * Get the staff user who recorded this expense.
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the cash register entry associated with this cash expense.
     */
    public function cashRegisterTransaction(): HasOne
    {
        return $this->hasOne(CashRegisterTransaction::class, 'expense_id');
    }
}

