<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Room;
use App\Models\RoomMaintenance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomMaintenanceController extends Controller
{
    /**
     * Display a listing of room maintenance records.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RoomMaintenance::query()->with(['room.roomType', 'reportedBy', 'resolvedBy']);

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->query('room_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $records = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 20));

        return ApiResponse::success($records, 'Maintenance records retrieved successfully.');
    }

    /**
     * Schedule or report a new room maintenance issue.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after:start_date',
            'reason' => 'required|string|max:255',
            'status' => ['nullable', Rule::in(['SCHEDULED', 'IN_PROGRESS'])],
            'maintenance_cost' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $status = $validated['status'] ?? 'SCHEDULED';
        $today = Carbon::today()->format('Y-m-d');

        $record = RoomMaintenance::create([
            'room_id' => $validated['room_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'status' => $status,
            'maintenance_cost' => $validated['maintenance_cost'] ?? 0.00,
            'reported_by' => $request->user()->id,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        // If maintenance is in progress or starts today/in the past, set room operational status to MAINTENANCE
        $room = Room::find($validated['room_id']);
        if ($room) {
            if ($status === 'IN_PROGRESS' || $validated['start_date'] <= $today) {
                if ($room->operational_status !== 'OUT_OF_SERVICE') {
                    $room->update(['operational_status' => 'MAINTENANCE']);
                }
            }

            try {
                app(\App\Services\NotificationService::class)->notifyRoomMaintenanceReported($record, $room);
            } catch (\Throwable $e) {}
        }

        return ApiResponse::success($record->load(['room.roomType', 'reportedBy']), 'Maintenance scheduled successfully.', 201);
    }

    /**
     * Resolve/complete a maintenance record.
     */
    public function resolve(Request $request, RoomMaintenance $roomMaintenance): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['COMPLETED', 'CANCELLED'])],
            'maintenance_cost' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
            'room_operational_status' => ['nullable', Rule::in(['READY', 'CLEANING'])],
        ]);

        $roomMaintenance->update([
            'status' => $validated['status'],
            'maintenance_cost' => $validated['maintenance_cost'] ?? $roomMaintenance->maintenance_cost,
            'resolved_by' => $request->user()->id,
            'resolved_at' => Carbon::now(),
            'remarks' => $validated['remarks'] ?? $roomMaintenance->remarks,
        ]);

        // Set room status back to READY or CLEANING
        $nextStatus = $validated['room_operational_status'] ?? 'READY';
        $room = $roomMaintenance->room;
        if ($room && $room->operational_status === 'MAINTENANCE') {
            $room->update(['operational_status' => $nextStatus]);
        }

        return ApiResponse::success($roomMaintenance->fresh(['room.roomType', 'reportedBy', 'resolvedBy']), 'Maintenance marked as ' . strtolower($validated['status']) . '.');
    }
}

