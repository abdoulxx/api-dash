<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $action = $request->get('action');
        $userId = $request->get('user_id');
        $modelType = $request->get('model_type');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = AuditLog::with('user');

        // Filter by action
        if ($action) {
            $query->where('action', $action);
        }

        // Filter by user
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Filter by model type
        if ($modelType) {
            $query->where('model_type', $modelType);
        }

        // Filter by date range
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Display the specified audit log.
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::with('user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new AuditLogResource($log),
        ]);
    }

    /**
     * Get audit logs for a specific user
     */
    public function userLogs(int $userId): JsonResponse
    {
        $logs = AuditLog::with('user')
            ->where('user_id', $userId)
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get audit logs for a specific model
     */
    public function modelLogs(string $modelType, int $modelId): JsonResponse
    {
        $logs = AuditLog::with('user')
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
