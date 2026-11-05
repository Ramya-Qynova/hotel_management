<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingStatusHistory;
use App\Models\Guest;
use App\Models\HotelSetting;
use App\Models\Room;
use App\Models\RoomMaintenance;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HotelOperationsSeeder extends Seeder
{
    /**
     * Run the hotel operations seeds.
     */
    public function run(): void
    {
        $owner = User::first();
        $ownerId = $owner?->id;

        // 1. Hotel Settings
        $settings = [
            ['key' => 'hotel_name', 'value' => 'Grand Vista Hotel', 'group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel display name'],
            ['key' => 'hotel_tagline', 'value' => 'Stay Better. Feel at Home', 'group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel tagline/motto'],
            ['key' => 'hotel_email', 'value' => 'reservations@hotel.com', 'group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel contact email'],
            ['key' => 'hotel_phone', 'value' => '+91 (011) 4567-8900', 'group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel contact phone number'],
            ['key' => 'hotel_address', 'value' => 'Plot 42, Civil Lines, Jaipur, Rajasthan 302006', 'group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Physical property address'],
            ['key' => 'hotel_logo_url', 'value' => '', 'group' => 'GENERAL', 'type' => 'STRING', 'description' => 'Hotel branding logo image URL'],
            ['key' => 'check_in_time', 'value' => '14:00', 'group' => 'CHECK_IN_OUT', 'type' => 'STRING', 'description' => 'Default check-in time (2:00 PM)'],
            ['key' => 'check_out_time', 'value' => '11:00', 'group' => 'CHECK_IN_OUT', 'type' => 'STRING', 'description' => 'Default check-out time (11:00 AM)'],
            ['key' => 'tax_rate_percent', 'value' => '12.00', 'group' => 'TAX', 'type' => 'DECIMAL', 'description' => 'GST / tax rate percent'],
            ['key' => 'tax_name', 'value' => 'GST', 'group' => 'TAX', 'type' => 'STRING', 'description' => 'Tax naming label e.g. GST'],
            ['key' => 'hotel_gstin', 'value' => '08AABCG1234F1Z8', 'group' => 'TAX', 'type' => 'STRING', 'description' => 'Hotel GST / Tax Registration Number'],
            ['key' => 'currency_symbol', 'value' => '₹', 'group' => 'PRICING', 'type' => 'STRING', 'description' => 'Currency symbol displayed across invoices & UI'],
            ['key' => 'currency_code', 'value' => 'INR', 'group' => 'PRICING', 'type' => 'STRING', 'description' => 'Standard ISO currency code e.g. INR'],
            ['key' => 'invoice_prefix', 'value' => 'INV', 'group' => 'PRICING', 'type' => 'STRING', 'description' => 'Prefix code for generated invoice numbers'],
            ['key' => 'invoice_footer_note', 'value' => 'Thank you for choosing our hotel. We look forward to welcoming you again!', 'group' => 'PRICING', 'type' => 'STRING', 'description' => 'Standard footer note on printed invoices'],
            ['key' => 'advance_payment_percent', 'value' => '50.00', 'group' => 'PRICING', 'type' => 'DECIMAL', 'description' => 'Required advance payment percent'],
            ['key' => 'hold_expiration_minutes', 'value' => '30', 'group' => 'BOOKING', 'type' => 'INTEGER', 'description' => 'Hold duration for online booking'],
            ['key' => 'cancellation_policy', 'value' => 'Free cancellation up to 24 hours prior to check-in.', 'group' => 'POLICY', 'type' => 'STRING', 'description' => 'Hotel guest reservation cancellation terms'],
            ['key' => 'cancellation_grace_hours', 'value' => '24', 'group' => 'POLICY', 'type' => 'INTEGER', 'description' => 'Free cancellation window prior to check-in in hours'],
            ['key' => 'house_rules', 'value' => 'Valid government photo ID is required upon check-in.', 'group' => 'POLICY', 'type' => 'STRING', 'description' => 'General guest house rules and conduct policies'],
        ];

        foreach ($settings as $st) {
            HotelSetting::firstOrCreate(['key' => $st['key']], array_merge($st, ['updated_by' => $ownerId]));
        }

        // 2. Room Categories (Room Types)
        $acType = RoomType::firstOrCreate(
            ['slug' => 'ac-room'],
            [
                'name' => 'AC Room',
                'base_price_per_night' => 2500.00,
                'max_occupancy' => 2,
                'extra_person_charge' => 500.00,
                'extra_bed_charge' => 300.00,
                'amenities' => ['High-Speed WiFi', 'Air Conditioning', 'Flat TV', 'Hot & Cold Water', 'Queen Bed', 'Complimentary Water'],
                'description' => 'Comfortable air-conditioned room with modern amenities and attached private bath.',
                'is_active' => true,
            ]
        );

        $normalType = RoomType::firstOrCreate(
            ['slug' => 'normal-room'],
            [
                'name' => 'Normal Room',
                'base_price_per_night' => 1500.00,
                'max_occupancy' => 2,
                'extra_person_charge' => 400.00,
                'extra_bed_charge' => 250.00,
                'amenities' => ['High-Speed WiFi', 'Ceiling Fan', 'Flat TV', 'Attached Bath', 'Work Desk'],
                'description' => 'Economical and well-ventilated standard non-AC room with all essentials.',
                'is_active' => true,
            ]
        );

        $premiumType = RoomType::firstOrCreate(
            ['slug' => 'premium-suite'],
            [
                'name' => 'Premium Suite',
                'base_price_per_night' => 4500.00,
                'max_occupancy' => 4,
                'extra_person_charge' => 700.00,
                'extra_bed_charge' => 500.00,
                'amenities' => ['High-Speed WiFi', 'Dual AC', 'King Bed', 'City View Balcony', 'Mini Bar', 'Bathtub', 'Smart TV'],
                'description' => 'Spacious luxury suite with scenic balcony view, king bed and premium furnishing.',
                'is_active' => true,
            ]
        );

        // 3. Physical Rooms (20 initial rooms: 10 AC, 10 Normal)
        for ($i = 1; $i <= 10; $i++) {
            $num = sprintf('AC-1%02d', $i);
            $opStatus = ($num === 'AC-105') ? 'MAINTENANCE' : 'READY';

            Room::firstOrCreate(
                ['room_number' => $num],
                [
                    'room_type_id' => $acType->id,
                    'floor' => 1,
                    'operational_status' => $opStatus,
                    'is_active' => true,
                    'notes' => ($num === 'AC-105') ? 'Scheduled AC filter cleaning' : 'Floor 1 AC Wing',
                ]
            );
        }

        for ($i = 1; $i <= 10; $i++) {
            $num = sprintf('N-2%02d', $i);
            Room::firstOrCreate(
                ['room_number' => $num],
                [
                    'room_type_id' => $normalType->id,
                    'floor' => 2,
                    'operational_status' => 'READY',
                    'is_active' => true,
                    'notes' => 'Floor 2 Standard Wing',
                ]
            );
        }

        // Maintenance for AC-105
        $room105 = Room::where('room_number', 'AC-105')->first();
        if ($room105 && RoomMaintenance::where('room_id', $room105->id)->doesntExist()) {
            RoomMaintenance::create([
                'room_id' => $room105->id,
                'start_date' => Carbon::today()->subDay()->format('Y-m-d'),
                'end_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
                'reason' => 'Air conditioner servicing and coil cleaning',
                'status' => 'IN_PROGRESS',
                'maintenance_cost' => 850.00,
                'reported_by' => $ownerId ?? 1,
                'remarks' => 'Technician scheduled to complete today',
            ]);
        }

        // 4. Sample Guests
        $guest1 = Guest::firstOrCreate(
            ['phone' => '+919876543210'],
            [
                'first_name' => 'Priya',
                'last_name' => 'Sharma',
                'email' => 'priya.sharma@example.com',
                'id_type' => 'AADHAAR',
                'id_number' => '4321-8765-1092',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'country' => 'India',
            ]
        );

        $guest2 = Guest::firstOrCreate(
            ['phone' => '+919812345678'],
            [
                'first_name' => 'Amit',
                'last_name' => 'Kumar',
                'email' => 'amit.kumar@example.com',
                'id_type' => 'DRIVING_LICENSE',
                'id_number' => 'DL-0420110023456',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
            ]
        );

        $guest3 = Guest::firstOrCreate(
            ['phone' => '+919765432109'],
            [
                'first_name' => 'Sneha',
                'last_name' => 'Patel',
                'email' => 'sneha.patel@example.com',
                'id_type' => 'PASSPORT',
                'id_number' => 'P8923412',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'country' => 'India',
            ]
        );

        $guest4 = Guest::firstOrCreate(
            ['phone' => '+919898765432'],
            [
                'first_name' => 'Rohit',
                'last_name' => 'Mehta',
                'email' => 'rohit.mehta@example.com',
                'id_type' => 'VOTER_ID',
                'id_number' => 'ABC1234567',
                'city' => 'Jaipur',
                'state' => 'Rajasthan',
                'country' => 'India',
            ]
        );

        $guest5 = Guest::firstOrCreate(
            ['phone' => '+919988776655'],
            [
                'first_name' => 'Anjali',
                'last_name' => 'Singh',
                'email' => 'anjali.singh@example.com',
                'id_type' => 'AADHAAR',
                'id_number' => '9988-7766-5544',
                'city' => 'Lucknow',
                'state' => 'Uttar Pradesh',
                'country' => 'India',
            ]
        );

        // 5. Seed Bookings if no bookings exist yet
        if (Booking::count() === 0) {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $tomorrow = Carbon::tomorrow();
            $dayAfter = Carbon::today()->addDays(2);

            // Active Occupied Rooms: 7 AC + 7 Normal = 14 occupied (matching reference image: 14/20 Occupied 70%)
            $acRooms = Room::where('room_type_id', $acType->id)->where('room_number', '!=', 'AC-105')->orderBy('room_number')->get();
            $normalRooms = Room::where('room_type_id', $normalType->id)->orderBy('room_number')->get();

            // Occupy 7 AC rooms (e.g., AC-101, AC-102, AC-106, AC-107, AC-108, AC-109, AC-110)
            $occupiedAc = [$acRooms[0], $acRooms[1], $acRooms[4], $acRooms[5], $acRooms[6], $acRooms[7], $acRooms[8]];
            // Occupy 7 Normal rooms (e.g., N-201, N-202, N-205, N-206, N-207, N-208, N-209)
            $occupiedNormal = [$normalRooms[0], $normalRooms[1], $normalRooms[4], $normalRooms[5], $normalRooms[6], $normalRooms[7], $normalRooms[8]];

            $allOccupied = array_merge($occupiedAc, $occupiedNormal);
            $guests = [$guest1, $guest2, $guest3, $guest4, $guest5];

            // 1. Priya Sharma - AC-101 (Checked In)
            $this->createSeedBooking('BK-2025-0424-001', $guest1, $acRooms[0], $yesterday, $dayAfter, 'CHECKED_IN', $ownerId, $today);

            // 2. Amit Kumar - N-205 (Checked In)
            $this->createSeedBooking('BK-2025-0424-002', $guest2, $normalRooms[4], $yesterday, $tomorrow, 'CHECKED_IN', $ownerId, $today);

            // 3. Sneha Patel - AC-103 (Pending)
            $this->createSeedBooking('BK-2025-0424-003', $guest3, $acRooms[2], $tomorrow, $today->copy()->addDays(3), 'PENDING_PAYMENT', $ownerId, $today);

            // 4. Rohit Mehta - N-207 (Confirmed)
            $this->createSeedBooking('BK-2025-0424-004', $guest4, $normalRooms[6], $tomorrow, $today->copy()->addDays(2), 'CONFIRMED', $ownerId, $today);

            // 5. Anjali Singh - AC-107 (Pending)
            $this->createSeedBooking('BK-2025-0424-005', $guest5, $acRooms[6], $tomorrow, $today->copy()->addDays(3), 'PENDING_PAYMENT', $ownerId, $today);

            // Other occupied rooms to hit exact 14 occupied today
            $remOccupied = [
                $acRooms[1], $acRooms[4], $acRooms[5], $acRooms[7], $acRooms[8],
                $normalRooms[0], $normalRooms[1], $normalRooms[5], $normalRooms[7], $normalRooms[8]
            ];

            $idx = 6;
            foreach ($remOccupied as $room) {
                $g = $guests[$idx % count($guests)];
                $bNum = sprintf('BK-2025-0424-%03d', $idx);
                $this->createSeedBooking($bNum, $g, $room, $yesterday, $dayAfter, 'CHECKED_IN', $ownerId, $today);
                $idx++;
            }

            // Create past bookings for revenue chart curve over past 7 days
            for ($d = 6; $d >= 1; $d--) {
                $pDate = Carbon::today()->subDays($d);
                $revTarget = [6 => 10000, 5 => 18000, 4 => 22000, 3 => 28000, 2 => 26000, 1 => 35000][$d] ?? 20000;
                
                $b = Booking::create([
                    'booking_number' => sprintf('BK-HIST-%04d', rand(1000, 9999)),
                    'guest_id' => $guest1->id,
                    'booking_source' => 'WALK_IN',
                    'status' => 'CHECKED_OUT',
                    'check_in_date' => $pDate->copy()->subDays(2)->format('Y-m-d'),
                    'check_out_date' => $pDate->format('Y-m-d'),
                    'base_room_charges' => $revTarget * 0.89,
                    'total_tax_amount' => $revTarget * 0.11,
                    'grand_total' => $revTarget,
                    'created_by' => $ownerId,
                    'created_at' => $pDate->copy()->setTime(11, 0),
                    'updated_at' => $pDate->copy()->setTime(11, 0),
                ]);
            }
        }
    }

    protected function createSeedBooking(string $bookingNumber, Guest $guest, Room $room, Carbon $checkIn, Carbon $checkOut, string $status, ?int $userId, Carbon $today): void
    {
        $nights = $checkIn->diffInDays($checkOut) ?: 1;
        $rate = (float) $room->roomType->base_price_per_night;
        $subtotal = round($rate * $nights, 2);
        $tax = round(($subtotal * 12.00) / 100, 2);
        $grandTotal = round($subtotal + $tax, 2);

        $booking = Booking::create([
            'booking_number' => $bookingNumber,
            'guest_id' => $guest->id,
            'booking_source' => ($status === 'PENDING_PAYMENT') ? 'ONLINE' : 'WALK_IN',
            'status' => $status,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'actual_check_in_at' => ($status === 'CHECKED_IN') ? $checkIn->copy()->setTime(14, 0) : null,
            'hold_expires_at' => ($status === 'PENDING_PAYMENT') ? Carbon::now()->addMinutes(30) : null,
            'base_room_charges' => $subtotal,
            'total_tax_amount' => $tax,
            'grand_total' => $grandTotal,
            'required_advance_amount' => round($grandTotal * 0.5, 2),
            'created_by' => $userId,
            'created_at' => $today->copy()->setTime(9, 30),
            'updated_at' => $today->copy()->setTime(9, 30),
        ]);

        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_type_id' => $room->room_type_id,
            'room_id' => $room->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'rate_per_night' => $rate,
            'nights_count' => $nights,
            'subtotal' => $subtotal,
            'tax_rate_percent' => 12.00,
            'tax_amount' => $tax,
            'total_amount' => $grandTotal,
        ]);

        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => $status,
            'reason' => 'Seeded booking',
            'changed_by' => $userId,
        ]);
    }
}

