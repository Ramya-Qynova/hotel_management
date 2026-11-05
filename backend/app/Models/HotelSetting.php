<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelSetting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'hotel_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'description',
        'updated_by',
    ];

    /**
     * Get a setting value typed correctly, or fallback default.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'INTEGER' => (int) $setting->value,
            'DECIMAL' => (float) $setting->value,
            'BOOLEAN' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'JSON' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * Get the staff user who last updated this setting.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

