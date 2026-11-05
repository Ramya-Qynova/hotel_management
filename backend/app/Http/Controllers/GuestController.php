<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestController extends Controller
{
    /**
     * Display a listing of guests with search and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Guest::query()->withCount('bookings');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $guests = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 20));

        // Unhide identification info only for authorized roles
        $user = $request->user();
        if ($user && $user->hasRole('OWNER', 'ADMIN', 'RECEPTIONIST')) {
            $guests->getCollection()->transform(function ($guest) {
                $guest->makeVisible(['id_number', 'id_document_path']);
                return $guest;
            });
        }

        return ApiResponse::success($guests, 'Guests retrieved successfully.');
    }

    /**
     * Store a newly created guest.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('phone')) {
            $request->merge(['phone' => trim((string) $request->input('phone'))]);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'id_type' => ['nullable', Rule::in(['AADHAAR', 'PASSPORT', 'DRIVING_LICENSE', 'VOTER_ID', 'OTHER'])],
            'id_number' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'special_requests' => 'nullable|string|max:1000',
        ], [
            'phone.regex' => 'The phone number must contain exactly 10 numeric digits.',
        ]);

        $guest = Guest::create($validated);

        $user = $request->user();
        if ($user && $user->hasRole('OWNER', 'ADMIN', 'RECEPTIONIST')) {
            $guest->makeVisible(['id_number', 'id_document_path']);
        }

        return ApiResponse::success($guest, 'Guest created successfully.', 201);
    }

    /**
     * Display the specified guest and their booking history.
     */
    public function show(Request $request, Guest $guest): JsonResponse
    {
        $guest->load([
            'bookings' => function ($q) {
                $q->latest()->with('bookingRooms.room.roomType');
            },
        ]);

        $user = $request->user();
        if ($user && $user->hasRole('OWNER', 'ADMIN', 'RECEPTIONIST')) {
            $guest->makeVisible(['id_number', 'id_document_path']);
        }

        return ApiResponse::success($guest, 'Guest profile retrieved.');
    }

    /**
     * Update the specified guest.
     */
    public function update(Request $request, Guest $guest): JsonResponse
    {
        if ($request->has('phone')) {
            $request->merge(['phone' => trim((string) $request->input('phone'))]);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => ['sometimes', 'required', 'string', 'regex:/^[0-9]{10}$/'],
            'id_type' => ['nullable', Rule::in(['AADHAAR', 'PASSPORT', 'DRIVING_LICENSE', 'VOTER_ID', 'OTHER'])],
            'id_number' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'special_requests' => 'nullable|string|max:1000',
        ], [
            'phone.regex' => 'The phone number must contain exactly 10 numeric digits.',
        ]);

        $guest->update($validated);

        $user = $request->user();
        if ($user && $user->hasRole('OWNER', 'ADMIN', 'RECEPTIONIST')) {
            $guest->makeVisible(['id_number', 'id_document_path']);
        }

        return ApiResponse::success($guest->fresh(), 'Guest updated successfully.');
    }
}

