<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the role assigned to the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Check if the user has a specific role or any of the given roles.
     *
     * @param string|array<string> ...$roles
     */
    public function hasRole(string|array ...$roles): bool
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }

        if (! $this->role) {
            return false;
        }

        $rolesToCheck = [];
        foreach ($roles as $role) {
            if (is_array($role)) {
                $rolesToCheck = array_merge($rolesToCheck, $role);
            } else {
                $rolesToCheck[] = $role;
            }
        }

        $rolesUpper = array_map(fn ($r) => strtoupper(trim((string) $r)), $rolesToCheck);

        return in_array(strtoupper(trim((string) $this->role->name)), $rolesUpper, true);
    }

    /**
     * Check if user is an OWNER.
     */
    public function isOwner(): bool
    {
        return $this->hasRole('OWNER');
    }

    /**
     * Check if user is an ADMIN.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('ADMIN');
    }

    /**
     * Check if user is a RECEPTIONIST.
     */
    public function isReceptionist(): bool
    {
        return $this->hasRole('RECEPTIONIST');
    }

    /**
     * Check if user is HOUSEKEEPING.
     */
    public function isHousekeeping(): bool
    {
        return $this->hasRole('HOUSEKEEPING');
    }

    /**
     * Check if user is an ACCOUNTANT.
     */
    public function isAccountant(): bool
    {
        return $this->hasRole('ACCOUNTANT');
    }

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Scope query to only active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the maintenance records reported by this user.
     */
    public function reportedMaintenances(): HasMany
    {
        return $this->hasMany(RoomMaintenance::class, 'reported_by');
    }

    /**
     * Get the maintenance records resolved by this user.
     */
    public function resolvedMaintenances(): HasMany
    {
        return $this->hasMany(RoomMaintenance::class, 'resolved_by');
    }

    /**
     * Get the bookings created by this staff user.
     */
    public function createdBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'created_by');
    }

    /**
     * Get the bookings cancelled by this staff user.
     */
    public function cancelledBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'cancelled_by');
    }

    /**
     * Get the booking status transitions logged by this staff user.
     */
    public function bookingStatusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class, 'changed_by');
    }

    /**
     * Get the payments received by this staff user.
     */
    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    /**
     * Get the refunds processed by this staff user.
     */
    public function processedRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'processed_by');
    }

    /**
     * Get the cash register transactions performed by this staff user.
     */
    public function cashRegisterTransactions(): HasMany
    {
        return $this->hasMany(CashRegisterTransaction::class, 'performed_by');
    }

    /**
     * Get the booking services added by this staff user.
     */
    public function addedBookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class, 'added_by');
    }

    /**
     * Get the invoices generated by this staff user.
     */
    public function generatedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'generated_by');
    }

    /**
     * Get the expenses recorded by this staff user.
     */
    public function recordedExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'recorded_by');
    }

    /**
     * Get the hotel settings updated by this staff user.
     */
    public function updatedHotelSettings(): HasMany
    {
        return $this->hasMany(HotelSetting::class, 'updated_by');
    }

    /**
     * Get the personal notifications for this staff user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    /**
     * Get the audit logs initiated by this staff user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
