<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bookings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_number',
        'guest_id',
        'booking_source',
        'status',
        'check_in_date',
        'check_out_date',
        'expected_check_in_time',
        'actual_check_in_at',
        'actual_check_out_at',
        'late_check_out_until',
        'adults_count',
        'children_count',
        'hold_expires_at',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'special_requests',
        'base_room_charges',
        'total_service_charges',
        'total_tax_amount',
        'total_discount_amount',
        'grand_total',
        'required_advance_amount',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'actual_check_in_at' => 'datetime',
            'actual_check_out_at' => 'datetime',
            'late_check_out_until' => 'datetime',
            'adults_count' => 'integer',
            'children_count' => 'integer',
            'hold_expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'base_room_charges' => 'decimal:2',
            'total_service_charges' => 'decimal:2',
            'total_tax_amount' => 'decimal:2',
            'total_discount_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'required_advance_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the guest who owns this booking.
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    /**
     * Get the staff user who created the booking (null if online).
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the staff user who cancelled the booking.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Get the room allocations for this booking.
     */
    public function bookingRooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class, 'booking_id');
    }

    /**
     * Get the lifecycle status transitions for this booking.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class, 'booking_id');
    }

    /**
     * Get the payments made for this booking.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'booking_id');
    }

    /**
     * Get the refunds processed for this booking.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'booking_id');
    }

    /**
     * Get the service orders associated with this booking.
     */
    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class, 'booking_id');
    }

    /**
     * Get the invoice issued for this booking (one invoice per booking).
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'booking_id');
    }
}

