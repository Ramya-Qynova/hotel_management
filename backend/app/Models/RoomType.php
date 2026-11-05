<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'room_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'base_price_per_night',
        'max_occupancy',
        'extra_person_charge',
        'extra_bed_charge',
        'amenities',
        'description',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price_per_night' => 'decimal:2',
            'max_occupancy' => 'integer',
            'extra_person_charge' => 'decimal:2',
            'extra_bed_charge' => 'decimal:2',
            'amenities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the rooms of this room type.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'room_type_id');
    }

    /**
     * Get the booking room allocations for this room type.
     */
    public function bookingRooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class, 'room_type_id');
    }
}

