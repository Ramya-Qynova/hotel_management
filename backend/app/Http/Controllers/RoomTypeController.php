<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoomTypeController extends Controller
{
    /**
     * Display a listing of room categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RoomType::query()->withCount('rooms');

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $roomTypes = $query->orderBy('name')->get();

        return ApiResponse::success($roomTypes, 'Room categories retrieved successfully.');
    }

    /**
     * Store a newly created room category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:room_types,name',
            'slug' => 'nullable|string|max:100|unique:room_types,slug',
            'base_price_per_night' => 'required|numeric|min:0',
            'max_occupancy' => 'required|integer|min:1|max:20',
            'extra_person_charge' => 'nullable|numeric|min:0',
            'extra_bed_charge' => 'nullable|numeric|min:0',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:100',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $roomType = RoomType::create($validated);

        return ApiResponse::success($roomType, 'Room category created successfully.', 201);
    }

    /**
     * Display the specified room category.
     */
    public function show(RoomType $roomType): JsonResponse
    {
        $roomType->load(['rooms' => function ($q) {
            $q->orderBy('room_number');
        }]);

        return ApiResponse::success($roomType, 'Room category details retrieved.');
    }

    /**
     * Update the specified room category.
     */
    public function update(Request $request, RoomType $roomType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('room_types', 'name')->ignore($roomType->id)],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('room_types', 'slug')->ignore($roomType->id)],
            'base_price_per_night' => 'sometimes|required|numeric|min:0',
            'max_occupancy' => 'sometimes|required|integer|min:1|max:20',
            'extra_person_charge' => 'nullable|numeric|min:0',
            'extra_bed_charge' => 'nullable|numeric|min:0',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:100',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['name']) && empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $roomType->update($validated);

        return ApiResponse::success($roomType->fresh(), 'Room category updated successfully.');
    }

    /**
     * Remove or deactivate a room category.
     */
    public function destroy(RoomType $roomType): JsonResponse
    {
        if ($roomType->rooms()->count() > 0) {
            // Cannot hard delete if rooms exist; deactivate instead
            $roomType->update(['is_active' => false]);
            return ApiResponse::success($roomType, 'Room category has active rooms, so it was deactivated rather than deleted.');
        }

        $roomType->delete();
        return ApiResponse::success(null, 'Room category deleted successfully.');
    }
}

