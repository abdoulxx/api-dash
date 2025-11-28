<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Audit Logs",
 *     description="Gestion des journaux d audit : consultation, filtrage par utilisateur, modele, action et dates, groupement par date."
 * )
 */
class AuditLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/audit-logs",
     *     summary="Lister les journaux d audit",
     *     description="Recupere une liste paginee des journaux d audit avec possibilite de filtrage par action, utilisateur, type de modele, dates et recherche. Supporte le groupement par date.",
     *     operationId="listAuditLogs",
     *     tags={"Audit Logs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numero de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d elements par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Filtrer par action (peut etre multiple, separees par virgule)",
     *         required=false,
     *         @OA\Schema(type="string", example="create,update,delete")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filtrer par ULID de l utilisateur",
     *         required=false,
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Parameter(
     *         name="model_type",
     *         in="query",
     *         description="Filtrer par type de modele (ex: User, Role, Permission)",
     *         required=false,
     *         @OA\Schema(type="string", example="User")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Date de debut (format: YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Date de fin (format: YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche dans la description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="group_by_date",
     *         in="query",
     *         description="Grouper les resultats par date",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des journaux d audit",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="150 journal(aux) d audit trouve(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=10),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=150)
     *             )
     *         )
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/audit-logs/{id}",
     *     summary="Afficher un journal d audit",
     *     description="Recupere les details complets d un journal d audit specifique, incluant les informations sur l utilisateur associe.",
     *     operationId="showAuditLog",
     *     tags={"Audit Logs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du journal d audit",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Details du journal d audit",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Journal d audit charge"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Journal d audit non trouve")
     * )
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
     * @OA\Get(
     *     path="/api/audit-logs/user/{userId}",
     *     summary="Recuperer les journaux d audit d un utilisateur",
     *     description="Recupere tous les journaux d audit pour un utilisateur specifique. Par defaut, les resultats sont groupes par date. Supporte le filtrage par action et dates.",
     *     operationId="getUserAuditLogs",
     *     tags={"Audit Logs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numero de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d elements par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Filtrer par action (peut etre multiple, separees par virgule)",
     *         required=false,
     *         @OA\Schema(type="string", example="create,update")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Date de debut (format: YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Date de fin (format: YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="group_by_date",
     *         in="query",
     *         description="Grouper les resultats par date (par defaut: true)",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Journaux d audit de l utilisateur",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="25 activite(s) enregistree(s) pour cet utilisateur"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=2),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=25)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouve")
     * )
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
     * @OA\Get(
     *     path="/api/audit-logs/action-types",
     *     summary="Recuperer les types d actions disponibles",
     *     description="Recupere la liste de tous les types d actions distincts disponibles dans les journaux d audit, avec leurs libelles en francais pour le filtrage.",
     *     operationId="getAuditLogActionTypes",
     *     tags={"Audit Logs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des types d actions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="12 type(s) d action disponible(s) pour le filtrage"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="create"),
     *                     @OA\Property(property="label", type="string", example="Creation")
     *                 )
     *             )
     *         )
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/audit-logs/model/{modelType}/{modelId}",
     *     summary="Recuperer les journaux d audit d un modele specifique",
     *     description="Recupere tous les journaux d audit associes a un modele specifique (ex: User, Role, Permission) et son ID.",
     *     operationId="getModelAuditLogs",
     *     tags={"Audit Logs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="modelType",
     *         in="path",
     *         required=true,
     *         description="Type du modele (ex: User, Role, Permission)",
     *         @OA\Schema(type="string", example="User")
     *     ),
     *     @OA\Parameter(
     *         name="modelId",
     *         in="path",
     *         required=true,
     *         description="ID du modele (peut etre un entier ou un ULID selon le type)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Journaux d audit du modele",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="8 journal(aux) d audit trouve(s) pour ce User"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=8)
     *             )
     *         )
     *     )
     * )
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
