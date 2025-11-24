<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'audit-logs:index:' . md5($request->fullUrl());
        
        $payload = \App\Support\CacheTagger::tags(['audit-logs'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $action = $request->get('action');
            $userId = $request->get('user_id');
            $modelType = $request->get('model_type');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $search = $request->get('search');
            $groupByDate = $request->get('group_by_date', false);

            $query = AuditLog::with('user');

        // Filter by action (can be multiple actions separated by comma)
        if ($action) {
            $actions = is_array($action) ? $action : explode(',', $action);
            $query->whereIn('action', $actions);
        }

        // Filter by user
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Filter by model type
        if ($modelType) {
            $query->where('model_type', $modelType);
        }

        // Search in description
        if ($search) {
            $query->where('description', 'like', "%{$search}%");
        }

        // Filter by date range
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->latest()->paginate($perPage);

        $data = AuditLogResource::collection($logs);

        // Group by date if requested
        if ($groupByDate) {
            $grouped = $data->collection->groupBy(function ($log) {
                return $log->resource->created_at?->format('Y-m-d');
            })->map(function ($dayLogs, $date) {
                try {
                    $parsedDate = Carbon::parse($date);
                    if (!$parsedDate || !($parsedDate instanceof Carbon)) {
                        $formattedDate = $date; // Fallback to raw date
                    } else {
                        $formattedDate = $parsedDate->setLocale('fr')->isoFormat('dddd, D MMMM YYYY');
                    }
                } catch (\Exception $e) {
                    $formattedDate = $date; // Fallback to raw date
                }
                
                return [
                    'date' => $date,
                    'formatted_date' => $formattedDate,
                    'logs' => $dayLogs->values(),
                ];
            })->values();
            
            $message = $logs->total() > 0 
                ? "{$logs->total()} journal(aux) d'audit trouvé(s), groupé(s) par date" 
                : 'Aucun journal d\'audit trouvé';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $grouped,
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
            ];
        }

        $message = $logs->total() > 0 
            ? "{$logs->total()} journal(aux) d'audit trouvé(s)" 
            : 'Aucun journal d\'audit trouvé';

        return [
            'status' => 200,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ];
        });

        return response()->json($payload);
    }

    /**
     * Display the specified audit log.
     */
    public function show(string $id): JsonResponse
    {
        $log = AuditLog::with('user')->findOrFail($id);
        
        // Determine user name or identifier
        if ($log->user) {
            $userName = $log->user->firstname && $log->user->lastname 
                ? "{$log->user->firstname} {$log->user->lastname}" 
                : ($log->user->email ?? "ID: {$log->user_id}");
        } else {
            // User doesn't exist or was deleted
            if ($log->user_id) {
                // Try to find if user was soft deleted
                $deletedUser = \App\Models\User::withTrashed()->find($log->user_id);
                if ($deletedUser) {
                    $userName = $deletedUser->firstname && $deletedUser->lastname 
                        ? "{$deletedUser->firstname} {$deletedUser->lastname} (supprimé)" 
                        : ($deletedUser->email ?? "ID: {$log->user_id} (supprimé)");
                } else {
                    $userName = "ID: {$log->user_id} (n'existe plus)";
                }
            } else {
                $userName = "Système";
            }
        }

        return response()->json([
            'status' => 200,
            'message' => "Journal d'audit chargé",
            'data' => new AuditLogResource($log),
        ]);
    }

    /**
     * Get audit logs for a specific user
     */
    public function userLogs(string $userId, Request $request): JsonResponse
    {
        $cacheKey = 'audit-logs:user:' . $userId . ':' . md5($request->fullUrl());
        
        $payload = \App\Support\CacheTagger::tags(['audit-logs', 'users'])->remember($cacheKey, 300, function () use ($userId, $request) {
            $perPage = $request->get('per_page', 15);
            $action = $request->get('action');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $groupByDate = $request->get('group_by_date', true); // Default true for user logs

            $query = AuditLog::with('user')
                ->where('user_id', $userId);

        // Filter by action (can be multiple actions separated by comma)
        if ($action) {
            $actions = is_array($action) ? $action : explode(',', $action);
            $query->whereIn('action', $actions);
        }

        // Filter by date range
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->latest()->paginate($perPage);

        $data = AuditLogResource::collection($logs);

        // Group by date if requested (default for user logs)
        if ($groupByDate) {
            $grouped = $data->collection->groupBy(function ($log) {
                return $log->resource->created_at?->format('Y-m-d');
            })->map(function ($dayLogs, $date) {
                try {
                    $parsedDate = Carbon::parse($date);
                    if (!$parsedDate || !($parsedDate instanceof Carbon)) {
                        $formattedDate = $date; // Fallback to raw date
                    } else {
                        $parsedDate->setLocale('fr');
                        $formattedDate = $parsedDate->isoFormat('dddd, D MMMM YYYY');
                    }
                } catch (\Exception $e) {
                    $formattedDate = $date; // Fallback to raw date
                }
                
                return [
                    'date' => $date,
                    'formatted_date' => $formattedDate,
                    'logs' => $dayLogs->values(),
                ];
            })->values();
            
            $message = $logs->total() > 0 
                ? "{$logs->total()} activité(s) enregistrée(s) pour cet utilisateur" 
                : 'Aucune activité enregistrée pour cet utilisateur';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $grouped,
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
            ];
        }

        $message = $logs->total() > 0 
            ? "{$logs->total()} activité(s) enregistrée(s) pour cet utilisateur" 
            : 'Aucune activité enregistrée pour cet utilisateur';

        return [
            'status' => 200,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ];
        });

        return response()->json($payload);
    }

    /**
     * Get available action types for filtering
     */
    public function getActionTypes(): JsonResponse
    {
        $actions = AuditLog::distinct()
            ->whereNotNull('action')
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->values();

        // Map to French labels
        $actionLabels = [
            'connexion d\'un utilisateur' => 'Connexion d\'un utilisateur',
            'login_failed' => 'Tentative de connexion échouée',
            'logout' => 'Déconnexion',
            'create' => 'Création',
            'update' => 'Modification',
            'delete' => 'Suppression',
            'Recherche' => 'Recherche',
            'Exporter' => 'Exporter',
            'Impression d\'un document' => 'Impression',
            'Chat bot Messages' => 'Chat bot Messages',
            'role_assignment' => 'Assignation de rôle',
            'permission_assignment' => 'Assignation de permission',
            'access' => 'Accès',
        ];

        $formatted = $actions->map(function ($action) use ($actionLabels) {
            return [
                'value' => $action,
                'label' => $actionLabels[$action] ?? $action,
            ];
        });

        $count = $formatted->count();
        $message = $count > 0 
            ? "{$count} type(s) d'action disponible(s) pour le filtrage" 
            : 'Aucun type d\'action disponible';

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $formatted,
        ]);
    }

    /**
     * Get audit logs for a specific model
     */
    public function modelLogs(string $modelType, string $modelId): JsonResponse
    {
        $logs = AuditLog::with('user')
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->latest()
            ->paginate(15);

        $message = $logs->total() > 0 
            ? "{$logs->total()} journal(aux) d'audit trouvé(s) pour ce {$modelType}" 
            : "Aucun journal d'audit trouvé pour ce {$modelType}";

        return response()->json([
            'status' => 200,
            'message' => $message,
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
