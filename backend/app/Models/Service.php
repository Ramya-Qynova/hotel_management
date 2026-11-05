<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'services';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'unit_price',
        'tax_rate_percent',
        'is_active',
        'description',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'price',
    ];

    /**
     * Alias unit_price to price for frontend compatibility.
     */
    public function getPriceAttribute(): float
    {
        return (float) $this->unit_price;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'tax_rate_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the booking orders that included this service.
     */
    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class, 'service_id');
    }
}

