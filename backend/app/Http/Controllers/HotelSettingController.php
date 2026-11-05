<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\HotelSetting;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HotelSettingController extends Controller
{
    /**
     * Map of known settings with default groups, types, and descriptions.
     */
    protected array $settingDefinitions = [
        'hotel_name' => ['group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel display name'],
        'hotel_tagline' => ['group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel tagline or motto'],
        'hotel_email' => ['group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel contact email'],
        'hotel_phone' => ['group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel contact phone number'],
        'hotel_address' => ['group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Physical property address'],
        'hotel_logo_url' => ['group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel branding logo image URL'],
        'check_in_time' => ['group' => 'CHECK_IN_OUT', 'type' => 'STRING', 'description' => 'Default check-in time (HH:MM 24hr)'],
        'check_out_time' => ['group' => 'CHECK_IN_OUT', 'type' => 'STRING', 'description' => 'Default check-out time (HH:MM 24hr)'],
        'hold_expiration_minutes' => ['group' => 'BOOKING', 'type' => 'INTEGER', 'description' => 'Booking hold expiration duration in minutes'],
        'tax_rate_percent' => ['group' => 'TAX', 'type' => 'DECIMAL', 'description' => 'Applicable GST / VAT tax percentage'],
        'tax_name' => ['group' => 'TAX', 'type' => 'STRING', 'description' => 'Tax naming label e.g. GST'],
        'hotel_gstin' => ['group' => 'TAX', 'type' => 'STRING', 'description' => 'Hotel GST / Tax Registration Number'],
        'currency_symbol' => ['group' => 'PRICING', 'type' => 'STRING', 'description' => 'Currency symbol displayed across invoices & UI'],
        'currency_code' => ['group' => 'PRICING', 'type' => 'STRING', 'description' => 'Standard ISO currency code e.g. INR'],
        'invoice_prefix' => ['group' => 'PRICING', 'type' => 'STRING', 'description' => 'Prefix code for generated invoice numbers'],
        'invoice_footer_note' => ['group' => 'PRICING', 'type' => 'STRING', 'description' => 'Standard footer note on printed invoices'],
        'advance_payment_percent' => ['group' => 'PRICING', 'type' => 'DECIMAL', 'description' => 'Mandatory advance payment percentage required to confirm reservations'],
        'cancellation_policy' => ['group' => 'POLICY', 'type' => 'STRING', 'description' => 'Hotel guest reservation cancellation terms'],
        'cancellation_grace_hours' => ['group' => 'POLICY', 'type' => 'INTEGER', 'description' => 'Free cancellation window prior to check-in in hours'],
        'house_rules' => ['group' => 'POLICY', 'type' => 'STRING', 'description' => 'General guest house rules and conduct policies'],
    ];

    /**
     * Get all hotel settings organized by group and dictionary.
     */
    public function index(Request $request): JsonResponse
    {
        $settings = HotelSetting::with('updatedBy:id,name')->get();

        $dictionary = [];
        $grouped = [];

        foreach ($settings as $setting) {
            $typedValue = match ($setting->type) {
                'INTEGER' => (int) $setting->value,
                'DECIMAL' => (float) $setting->value,
                'BOOLEAN' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'JSON' => json_decode($setting->value, true),
                default => $setting->value,
            };

            $dictionary[$setting->key] = $typedValue;
            $grouped[$setting->group][] = [
                'id' => $setting->id,
                'key' => $setting->key,
                'value' => $typedValue,
                'group' => $setting->group,
                'type' => $setting->type,
                'description' => $setting->description,
                'updated_by' => $setting->updatedBy?->name,
                'updated_at' => $setting->updated_at?->toIso8601String(),
            ];
        }

        return ApiResponse::success([
            'settings' => $dictionary,
            'grouped' => $grouped,
        ], 'Hotel settings retrieved successfully.');
    }

    /**
     * Update or create multiple hotel settings in batch.
     */
    public function update(Request $request): JsonResponse
    {
        $input = $request->input('settings', $request->all());

        if (! is_array($input)) {
            return ApiResponse::validationError(['settings' => ['The settings payload must be an object or array.']]);
        }

        if (isset($input['hotel_phone']) && is_string($input['hotel_phone'])) {
            $input['hotel_phone'] = trim($input['hotel_phone']);
        }

        // Validate individual setting keys
        $validator = Validator::make($input, [
            'hotel_name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'hotel_tagline' => ['nullable', 'string', 'max:255'],
            'hotel_email' => ['nullable', 'email', 'max:150'],
            'hotel_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'hotel_address' => ['nullable', 'string', 'max:300'],
            'hotel_logo_url' => ['nullable', 'string', 'max:500'],
            'check_in_time' => ['sometimes', 'required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'check_out_time' => ['sometimes', 'required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'hold_expiration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:1440'],
            'tax_rate_percent' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'tax_name' => ['nullable', 'string', 'max:50'],
            'hotel_gstin' => ['nullable', 'string', 'max:50'],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'invoice_footer_note' => ['nullable', 'string', 'max:500'],
            'advance_payment_percent' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'cancellation_policy' => ['nullable', 'string', 'max:3000'],
            'cancellation_grace_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'house_rules' => ['nullable', 'string', 'max:3000'],
        ], [
            'check_in_time.regex' => 'The check-in time must be formatted as 24-hour HH:MM (e.g. 14:00).',
            'check_out_time.regex' => 'The check-out time must be formatted as 24-hour HH:MM (e.g. 11:00).',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray(), 'Settings validation failed.');
        }

        $userId = $request->user()->id;
        $updatedSettings = [];
        $oldValues = [];

        DB::transaction(function () use ($input, $userId, &$updatedSettings, &$oldValues) {
            foreach ($input as $key => $val) {
                // Ignore keys not defined or internal
                if (! array_key_exists($key, $this->settingDefinitions)) {
                    continue;
                }

                $meta = $this->settingDefinitions[$key];
                $existing = HotelSetting::where('key', $key)->first();
                $oldValues[$key] = $existing?->value;

                $stringVal = is_array($val) ? json_encode($val) : (string) $val;

                $setting = HotelSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $stringVal,
                        'group' => $meta['group'],
                        'type' => $meta['type'],
                        'description' => $meta['description'],
                        'updated_by' => $userId,
                    ]
                );

                $updatedSettings[$key] = match ($meta['type']) {
                    'INTEGER' => (int) $stringVal,
                    'DECIMAL' => (float) $stringVal,
                    'BOOLEAN' => filter_var($stringVal, FILTER_VALIDATE_BOOLEAN),
                    'JSON' => json_decode($stringVal, true),
                    default => $stringVal,
                };
            }

            if ($existingFirst = HotelSetting::first()) {
                AuditService::log(
                    'SETTINGS_UPDATED',
                    $existingFirst,
                    $oldValues,
                    $updatedSettings,
                    $userId
                );
            }
        });

        return ApiResponse::success($updatedSettings, 'Hotel settings saved successfully.');
    }
}

