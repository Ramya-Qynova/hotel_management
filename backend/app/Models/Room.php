<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rooms';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'room_number',
        'room_type_id',
        'floor',
        'operational_status',
        'is_active',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the room type of this room.
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    /**
     * Get the maintenance records for this room.
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(RoomMaintenance::class, 'room_id');
    }

    /**
     * Get the booking room allocations for this room.
     */
    public function bookingRooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class, 'room_id');
    }

    /**
     * Get the service orders assigned to this room.
     */
    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class, 'room_id');
    }
}

