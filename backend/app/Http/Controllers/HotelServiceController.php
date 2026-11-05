<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Service;
use App\Services\HotelServiceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HotelServiceController extends Controller
{
    public function __construct(
        protected HotelServiceManagementService $serviceManager
    ) {}

    /**
     * Display a listing of available hotel services.
     */
    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', false);
        $category = $request->query('category');
        $search = $request->query('search');

        $services = $this->serviceManager->getServices($activeOnly, $category, $search);

        return ApiResponse::success($services, 'Hotel services retrieved successfully.');
    }

    /**
     * Store a newly created hotel service.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('price') && !$request->has('unit_price')) {
            $request->merge(['unit_price' => $request->input('price')]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:services,name',
            'category' => ['required', Rule::in(['FOOD', 'LAUNDRY', 'EXTRA_BED', 'OTHER'])],
            'unit_price' => 'required|numeric|min:0',
            'tax_rate_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:255',
        ]);

        $service = $this->serviceManager->createService($validated);

        return ApiResponse::success($service, 'Service created successfully.', 201);
    }

    /**
     * Update the specified hotel service.
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        if ($request->has('price') && !$request->has('unit_price')) {
            $request->merge(['unit_price' => $request->input('price')]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('services', 'name')->ignore($service->id)],
            'category' => ['nullable', Rule::in(['FOOD', 'LAUNDRY', 'EXTRA_BED', 'OTHER'])],
            'unit_price' => 'sometimes|required|numeric|min:0',
            'tax_rate_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:255',
        ]);

        $updated = $this->serviceManager->updateService($service, $validated);

        return ApiResponse::success($updated, 'Service updated successfully.');
    }

    /**
     * Toggle active status of a service.
     */
    public function toggleActive(Service $service): JsonResponse
    {
        $service->update(['is_active' => !$service->is_active]);

        return ApiResponse::success($service->fresh(), 'Service status toggled.');
    }

    /**
     * Attach a service order to an existing booking.
     */
    public function addToBooking(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'room_id' => 'nullable|exists:rooms,id',
            'quantity' => 'required|integer|min:1|max:50',
            'service_date' => 'nullable|date_format:Y-m-d',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $bookingService = $this->serviceManager->addServiceToBooking($booking, $validated, $request->user());
            return ApiResponse::success($bookingService, 'Service charge added to reservation.', 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Remove a service order from a booking and reverse financial charges.
     */
    public function removeFromBooking(BookingService $bookingService): JsonResponse
    {
        $this->serviceManager->removeServiceFromBooking($bookingService);

        return ApiResponse::success(null, 'Service charge removed and booking totals recalculated.');
    }
}
