<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelSetting;
use App\Models\Room;
use App\Models\RoomMaintenance;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Compile complete dynamic hotel dashboard metrics.
     *
     * @param int $revenueDays Number of days for revenue trend (7, 30, 90)
     * @return array
     */
    public function getDashboardData(int $revenueDays = 7): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        // All active rooms
        $allRooms = Room::with('roomType')->where('is_active', true)->orderBy('room_number')->get();
        $totalRoomsCount = $allRooms->count();

        // Active room types
        $roomTypes = RoomType::where('is_active', true)->withCount('rooms')->get();

        // 1. Occupied rooms today (Checked-in bookings where today falls between check_in_date and check_out_date)
        $occupiedBookingRooms = BookingRoom::query()
            ->whereHas('booking', function ($q) {
                $q->where('status', 'CHECKED_IN');
            })
            ->where('check_in_date', '<=', $today)
            ->where('check_out_date', '>', $today)
            ->with(['booking.guest', 'room.roomType'])
            ->get();

        $occupiedRoomIds = $occupiedBookingRooms->pluck('room_id')->unique()->toArray();
        $occupiedCount = count($occupiedRoomIds);

        // Map occupied rooms to guest info for quick tooltip/overview lookup
        $occupiedRoomGuestMap = [];
        foreach ($occupiedBookingRooms as $br) {
            $occupiedRoomGuestMap[$br->room_id] = [
                'guest_name' => $br->booking?->guest ? "{$br->booking->guest->first_name} {$br->booking->guest->last_name}" : 'Guest',
                'booking_number' => $br->booking?->booking_number,
                'check_in' => $br->booking?->check_in_date?->format('d M, Y'),
                'check_out' => $br->booking?->check_out_date?->format('d M, Y'),
            ];
        }

        $occupancyRate = $totalRoomsCount > 0 ? round(($occupiedCount / $totalRoomsCount) * 100, 1) : 0.0;

        // 2. Available rooms count today: Active rooms not currently occupied and not in maintenance or out of service
        $activeMaintenanceRoomIds = RoomMaintenance::query()
            ->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])
            ->where('start_date', '<=', $today)
            ->where('end_date', '>', $today)
            ->pluck('room_id')
            ->toArray();

        $availableRooms = $allRooms->filter(function ($room) use ($occupiedRoomIds, $activeMaintenanceRoomIds) {
            return !in_array($room->id, $occupiedRoomIds, true)
                && !in_array($room->id, $activeMaintenanceRoomIds, true)
                && $room->operational_status !== 'OUT_OF_SERVICE';
        });
        $availableCount = $availableRooms->count();

        // Breakdown text for Total Rooms (e.g. "10 AC • 10 Normal")
        $totalBreakdownParts = [];
        $availableBreakdownParts = [];

        foreach ($roomTypes as $type) {
            $typeTotal = $allRooms->where('room_type_id', $type->id)->count();
            if ($typeTotal > 0) {
                $totalBreakdownParts[] = "{$typeTotal} {$type->name}";
            }

            $typeAvailable = $availableRooms->where('room_type_id', $type->id)->count();
            if ($typeTotal > 0) {
                $availableBreakdownParts[] = "{$typeAvailable} {$type->name}";
            }
        }

        $totalBreakdown = implode(' • ', $totalBreakdownParts);
        $availableBreakdown = implode(' • ', $availableBreakdownParts);

        // 3. Today's Check-ins
        $todayCheckInsCount = Booking::query()
            ->whereDate('check_in_date', $today)
            ->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'PENDING_PAYMENT'])
            ->count();

        $yesterdayCheckInsCount = Booking::query()
            ->whereDate('check_in_date', $yesterday)
            ->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'PENDING_PAYMENT'])
            ->count();

        $checkInDiff = $todayCheckInsCount - $yesterdayCheckInsCount;
        $checkInDiffStr = ($checkInDiff >= 0 ? "+{$checkInDiff}" : "{$checkInDiff}");

        // 4. Today's Check-outs
        $todayCheckOutsCount = Booking::query()
            ->whereDate('check_out_date', $today)
            ->whereIn('status', ['CHECKED_IN', 'CHECKED_OUT'])
            ->count();

        $yesterdayCheckOutsCount = Booking::query()
            ->whereDate('check_out_date', $yesterday)
            ->whereIn('status', ['CHECKED_IN', 'CHECKED_OUT'])
            ->count();

        $checkOutDiff = $todayCheckOutsCount - $yesterdayCheckOutsCount;
        $checkOutDiffStr = ($checkOutDiff >= 0 ? "+{$checkOutDiff}" : "{$checkOutDiff}");

        // 5. Today's Revenue (Bookings created today or checking in today with grand_total)
        $todayRevenue = (float) Booking::query()
            ->whereDate('created_at', $today)
            ->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT'])
            ->sum('grand_total');

        $yesterdayRevenue = (float) Booking::query()
            ->whereDate('created_at', $yesterday)
            ->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT'])
            ->sum('grand_total');

        $revenuePercentDiff = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($todayRevenue > 0 ? 100 : 0);
        $revenueDiffStr = ($revenuePercentDiff >= 0 ? "+{$revenuePercentDiff}%" : "{$revenuePercentDiff}%");

        // 6. Revenue Overview Trend
        $revenueTrend = $this->buildRevenueTrend($revenueDays);

        // 7. Room Status Donut / Radial Gauges per Room Type
        $roomStatusDonuts = [];
        foreach ($roomTypes as $type) {
            $typeRooms = $allRooms->where('room_type_id', $type->id);
            $typeTotal = $typeRooms->count();
            if ($typeTotal === 0) {
                continue;
            }

            $typeOccupied = $typeRooms->filter(fn ($r) => in_array($r->id, $occupiedRoomIds, true))->count();
            $typeAvailable = $typeTotal - $typeOccupied;
            $typeOccupancyPct = round(($typeOccupied / $typeTotal) * 100);

            $roomStatusDonuts[] = [
                'room_type_id' => $type->id,
                'name' => $type->name,
                'total' => $typeTotal,
                'occupied' => $typeOccupied,
                'available' => $typeAvailable,
                'percentage' => $typeOccupancyPct,
            ];
        }

        // 8. Recent Bookings (up to 8)
        $recentBookings = Booking::query()
            ->with(['guest', 'bookingRooms.room.roomType'])
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function ($b) {
                $roomsList = $b->bookingRooms->map(fn ($br) => $br->room?->room_number ?? 'N/A')->implode(', ');
                $guestName = $b->guest ? "{$b->guest->first_name} {$b->guest->last_name}" : 'Walk-in Guest';

                return [
                    'id' => $b->id,
                    'booking_number' => $b->booking_number,
                    'guest_name' => $guestName,
                    'rooms' => $roomsList ?: 'Unassigned',
                    'check_in' => $b->check_in_date ? $b->check_in_date->format('d M, Y') : '',
                    'check_out' => $b->check_out_date ? $b->check_out_date->format('d M, Y') : '',
                    'status' => $b->status,
                    'grand_total' => (float) $b->grand_total,
                ];
            });

        // 9. Room Overview Grid (all active rooms with real status)
        $roomOverview = $allRooms->map(function ($room) use ($occupiedRoomIds, $activeMaintenanceRoomIds, $occupiedRoomGuestMap) {
            $isOccupied = in_array($room->id, $occupiedRoomIds, true);
            $isInMaintenance = in_array($room->id, $activeMaintenanceRoomIds, true) || $room->operational_status === 'MAINTENANCE';
            $isCleaning = $room->operational_status === 'CLEANING';
            $isOutOfService = $room->operational_status === 'OUT_OF_SERVICE';

            if ($isInMaintenance) {
                $status = 'Maintenance';
            } elseif ($isOutOfService) {
                $status = 'Out of Service';
            } elseif ($isCleaning) {
                $status = 'Cleaning';
            } elseif ($isOccupied) {
                $status = 'Occupied';
            } else {
                $status = 'Available';
            }

            return [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'room_type_name' => $room->roomType?->name ?? 'Room',
                'floor' => $room->floor,
                'operational_status' => $room->operational_status,
                'is_occupied' => $isOccupied,
                'status' => $status,
                'guest_info' => $isOccupied ? ($occupiedRoomGuestMap[$room->id] ?? null) : null,
            ];
        });

        $currencySymbol = HotelSetting::getValue('currency_symbol', '₹');

        return [
            'metrics' => [
                'total_rooms' => [
                    'value' => $totalRoomsCount,
                    'breakdown' => $totalBreakdown,
                ],
                'occupied_rooms' => [
                    'value' => $occupiedCount,
                    'occupancy_rate' => $occupancyRate,
                    'label' => "{$occupancyRate}% Occupancy",
                ],
                'available_rooms' => [
                    'value' => $availableCount,
                    'breakdown' => $availableBreakdown,
                ],
                'today_check_ins' => [
                    'value' => $todayCheckInsCount,
                    'trend' => $checkInDiffStr,
                ],
                'today_check_outs' => [
                    'value' => $todayCheckOutsCount,
                    'trend' => $checkOutDiffStr,
                ],
                'today_revenue' => [
                    'value' => $todayRevenue,
                    'formatted' => $currencySymbol . number_format($todayRevenue, 0),
                    'trend' => $revenueDiffStr,
                ],
            ],
            'revenue_overview' => [
                'days' => $revenueDays,
                'data' => $revenueTrend,
            ],
            'room_status' => $roomStatusDonuts,
            'recent_bookings' => $recentBookings,
            'room_overview' => $roomOverview,
            'hotel_info' => [
                'name' => HotelSetting::getValue('hotel_name', 'Hotel Operations'),
                'tagline' => HotelSetting::getValue('hotel_tagline', 'Staff Management Portal'),
                'status' => 'Hotel Open',
                'check_in_time' => HotelSetting::getValue('check_in_time', '14:00'),
                'check_out_time' => HotelSetting::getValue('check_out_time', '11:00'),
                'current_date' => Carbon::now()->format('D, M d, Y'),
            ],
        ];
    }

    /**
     * Build daily revenue data series for given number of days.
     */
    protected function buildRevenueTrend(int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 7;
        $trend = [];
        $startDate = Carbon::today()->subDays($days - 1);

        // Fetch bookings grouped by date
        $dailyRevenues = Booking::query()
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as total')
            ->where('created_at', '>=', $startDate->copy()->startOfDay())
            ->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'date')
            ->toArray();

        $currencySymbol = HotelSetting::getValue('currency_symbol', '₹');

        for ($i = 0; $i < $days; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dateKey = $currentDate->format('Y-m-d');
            $label = $currentDate->format('M d');
            $amount = isset($dailyRevenues[$dateKey]) ? (float) $dailyRevenues[$dateKey] : 0.0;

            $trend[] = [
                'date' => $dateKey,
                'label' => $label,
                'revenue' => $amount,
                'formatted' => $currencySymbol . number_format($amount, 0),
            ];
        }

        return $trend;
    }
}

