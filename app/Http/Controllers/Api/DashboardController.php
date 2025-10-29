<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function index(): JsonResponse
    {
        $stats = [
            'total_users' => User::where('is_admin', false)->count(),
            'total_admins' => User::where('is_admin', true)->count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'total_roles' => Role::count(),
            'total_permissions' => Permission::count(),
            'total_audit_logs' => AuditLog::count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get recent activities
     */
    public function recentActivities(): JsonResponse
    {
        $recentLogs = AuditLog::with('user')
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $recentLogs,
        ]);
    }

    /**
     * Get user growth statistics
     */
    public function userGrowth(): JsonResponse
    {
        $userGrowth = User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $userGrowth,
        ]);
    }

    /**
     * Get action statistics
     */
    public function actionStats(): JsonResponse
    {
        $actionStats = AuditLog::select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $actionStats,
        ]);
    }

    /**
     * Get top active users
     */
    public function topActiveUsers(): JsonResponse
    {
        $topUsers = AuditLog::select('user_id', DB::raw('COUNT(*) as activity_count'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('activity_count')
            ->limit(10)
            ->with('user')
            ->get()
            ->map(function ($log) {
                return [
                    'user' => $log->user,
                    'activity_count' => $log->activity_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $topUsers,
        ]);
    }
}
