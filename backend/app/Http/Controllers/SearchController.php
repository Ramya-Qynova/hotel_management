<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search bookings, guests, and rooms simultaneously.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (strlen($q) < 1) {
            return ApiResponse::success([
                'bookings' => [],
                'guests' => [],
                'rooms' => [],
            ], 'Query too short.');
        }

        // 1. Search Bookings
        $bookings = Booking::query()
            ->where('booking_number', 'like', "%{$q}%")
            ->orWhereHas('guest', function ($gq) use ($q) {
                $gq->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->with(['guest', 'bookingRooms.room.roomType'])
            ->limit(8)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'booking_number' => $b->booking_number,
                'guest_name' => $b->guest ? "{$b->guest->first_name} {$b->guest->last_name}" : 'Guest',
                'status' => $b->status,
                'grand_total' => (float) $b->grand_total,
                'check_in' => $b->check_in_date?->format('d M, Y'),
                'check_out' => $b->check_out_date?->format('d M, Y'),
            ]);

        // 2. Search Guests
        $guests = Guest::query()
            ->where('first_name', 'like', "%{$q}%")
            ->orWhere('last_name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%")
            ->orWhere('phone', 'like', "%{$q}%")
            ->orWhere('id_number', 'like', "%{$q}%")
            ->limit(8)
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => "{$g->first_name} {$g->last_name}",
                'email' => $g->email,
                'phone' => $g->phone,
                'vip_status' => (bool) $g->vip_status,
            ]);

        // 3. Search Rooms
        $rooms = Room::query()
            ->where('room_number', 'like', "%{$q}%")
            ->orWhereHas('roomType', function ($rt) use ($q) {
                $rt->where('name', 'like', "%{$q}%");
            })
            ->with('roomType')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'room_number' => $r->room_number,
                'room_type_name' => $r->roomType?->name,
                'floor' => $r->floor,
                'operational_status' => $r->operational_status,
            ]);

        return ApiResponse::success([
            'bookings' => $bookings,
            'guests' => $guests,
            'rooms' => $rooms,
        ], 'Search results compiled.');
    }
}

