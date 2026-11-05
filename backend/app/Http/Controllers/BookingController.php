<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\HotelSetting;
use App\Models\Room;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Display a paginated listing of bookings with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()->with(['guest', 'bookingRooms.room.roomType', 'createdBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('booking_source')) {
            $query->where('booking_source', $request->query('booking_source'));
        }

        if ($request->filled('check_in_date')) {
            $query->whereDate('check_in_date', $request->query('check_in_date'));
        }

        if ($request->filled('check_out_date')) {
            $query->whereDate('check_out_date', $request->query('check_out_date'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('booking_number', 'like', "%{$search}%")
                  ->orWhereHas('guest', function ($gq) use ($search) {
                      $gq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $bookings = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::success($bookings, 'Bookings retrieved successfully.');
    }

    /**
     * Store a newly created booking.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('guest.phone')) {
            $guestData = (array) $request->input('guest');
            if (isset($guestData['phone'])) {
                $guestData['phone'] = trim((string) $guestData['phone']);
                $request->merge(['guest' => $guestData]);
            }
        }

        $validated = $request->validate([
            'guest_id' => 'nullable|exists:guests,id',
            'guest' => 'nullable|array|required_without:guest_id',
            'guest.first_name' => 'required_with:guest|string|max:100',
            'guest.last_name' => 'required_with:guest|string|max:100',
            'guest.phone' => ['required_with:guest', 'string', 'regex:/^[0-9]{10}$/'],
            'guest.email' => 'nullable|email|max:150',
            'guest.id_type' => ['nullable', Rule::in(['AADHAAR', 'PASSPORT', 'DRIVING_LICENSE', 'VOTER_ID', 'OTHER'])],
            'guest.id_number' => 'nullable|string|max:100',
            'booking_source' => ['nullable', Rule::in(['WALK_IN', 'ONLINE'])],
            'status' => ['nullable', Rule::in(['CONFIRMED', 'PENDING_PAYMENT', 'CHECKED_IN'])],
            'check_in_date' => 'required|date_format:Y-m-d',
            'check_out_date' => 'required|date_format:Y-m-d|after:check_in_date',
            'room_ids' => 'required|array|min:1',
            'room_ids.*' => 'integer|exists:rooms,id',
            'adults_count' => 'nullable|integer|min:1|max:30',
            'children_count' => 'nullable|integer|min:0|max:20',
            'expected_check_in_time' => 'nullable|string',
            'special_requests' => 'nullable|string|max:1000',
        ], [
            'guest.phone.regex' => 'The guest phone number must contain exactly 10 numeric digits.',
        ]);

        // If guest object was supplied inline, create or reuse guest by phone
        if (empty($validated['guest_id']) && !empty($validated['guest'])) {
            $guestData = $validated['guest'];
            $guest = Guest::firstOrCreate(
                ['phone' => $guestData['phone']],
                $guestData
            );
            $validated['guest_id'] = $guest->id;
        }

        try {
            $booking = $this->bookingService->createBooking($validated, $request->user());
            return ApiResponse::success($booking, 'Booking created successfully.', 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Display the specified booking with room breakdown and status lifecycle.
     */
    public function show(Booking $booking): JsonResponse
    {
        $booking->load([
            'guest',
            'bookingRooms.room.roomType',
            'statusHistories.changedBy',
            'createdBy',
            'cancelledBy',
        ]);

        return ApiResponse::success($booking, 'Booking details retrieved.');
    }

    /**
     * Cancel an active booking.
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $cancelledBooking = $this->bookingService->cancelBooking($booking, $validated['reason'], $request->user());
            return ApiResponse::success($cancelledBooking, 'Booking has been cancelled.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Calculate room rates, tax, and totals for quotation before creating booking.
     */
    public function calculateRates(Request $request): JsonResponse
    {
        $request->validate([
            'check_in_date' => 'required|date_format:Y-m-d',
            'check_out_date' => 'required|date_format:Y-m-d|after:check_in_date',
            'room_ids' => 'required|array|min:1',
            'room_ids.*' => 'integer|exists:rooms,id',
        ]);

        $cIn = Carbon::parse($request->query('check_in_date'));
        $cOut = Carbon::parse($request->query('check_out_date'));
        $nights = $cIn->diffInDays($cOut);

        if ($nights < 1) {
            return ApiResponse::error('Check-out date must be at least 1 day after check-in date.', 422);
        }

        $taxRateSetting = HotelSetting::where('key', 'tax_rate_percent')->first();
        $taxRatePercent = $taxRateSetting ? (float) $taxRateSetting->value : 12.00;

        $rooms = Room::with('roomType')->whereIn('id', $request->query('room_ids'))->get();

        $lineItems = [];
        $subtotal = 0.00;
        $totalTax = 0.00;

        foreach ($rooms as $room) {
            $rate = (float) $room->roomType->base_price_per_night;
            $roomSubtotal = round($rate * $nights, 2);
            $roomTax = round(($roomSubtotal * $taxRatePercent) / 100, 2);
            $roomTotal = round($roomSubtotal + $roomTax, 2);

            $lineItems[] = [
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'room_type' => $room->roomType->name,
                'rate_per_night' => $rate,
                'nights' => $nights,
                'subtotal' => $roomSubtotal,
                'tax_amount' => $roomTax,
                'total_amount' => $roomTotal,
            ];

            $subtotal += $roomSubtotal;
            $totalTax += $roomTax;
        }

        $grandTotal = round($subtotal + $totalTax, 2);
        $advancePercentSetting = HotelSetting::where('key', 'advance_payment_percent')->first();
        $advancePercent = $advancePercentSetting ? (float) $advancePercentSetting->value : 50.00;
        $requiredAdvance = round(($grandTotal * $advancePercent) / 100, 2);

        return ApiResponse::success([
            'nights' => $nights,
            'tax_rate_percent' => $taxRatePercent,
            'subtotal' => $subtotal,
            'tax_amount' => $totalTax,
            'grand_total' => $grandTotal,
            'required_advance_amount' => $requiredAdvance,
            'rooms' => $lineItems,
        ], 'Calculation completed.');
    }
}

