<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DeclarationSg;
use App\Models\FcvrSg;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     * Cached for 5 minutes
     */
    public function index(): JsonResponse
    {
        $stats = Cache::remember('dashboard:stats', 300, function () {
            return [
                'total_users' => User::where('is_admin', false)->count(),
                'total_admins' => User::where('is_admin', true)->count(),
                'active_users' => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'total_roles' => Role::count(),
                'total_permissions' => Permission::count(),
                'total_audit_logs' => AuditLog::count(),
                'total_manifestes' => DB::table('manifeste_sg')->count(),
                'total_fdi' => DB::table('fdi_sg')->count(),
                'total_fcvr' => DB::table('fcvr_sg')->count(),
                'total_declarations' => DB::table('declaration_sg')->count(),
            ];
        });

        return response()->json([
            'status' => 200,
            'data' => $stats,
        ]);
    }

    /**
     * Get recent activities
     * Cached for 2 minutes
     */
    public function recentActivities(): JsonResponse
    {
        $recentLogs = Cache::remember('dashboard:recent_activities', 120, function () {
            return AuditLog::with('user')
                ->latest()
                ->limit(10)
                ->get();
        });

        return response()->json([
            'status' => 200,
            'data' => $recentLogs,
        ]);
    }

    /**
     * Get user growth statistics
     * Cached for 10 minutes
     */
    public function userGrowth(): JsonResponse
    {
        $userGrowth = Cache::remember('dashboard:user_growth', 600, function () {
            return User::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        });

        return response()->json([
            'status' => 200,
            'data' => $userGrowth,
        ]);
    }

    /**
     * Get action statistics
     * Cached for 5 minutes
     */
    public function actionStats(): JsonResponse
    {
        $actionStats = Cache::remember('dashboard:action_stats', 300, function () {
            return AuditLog::select('action', DB::raw('COUNT(*) as count'))
                ->groupBy('action')
                ->get();
        });

        return response()->json([
            'status' => 200,
            'data' => $actionStats,
        ]);
    }

    /**
     * Get top active users
     * Cached for 10 minutes
     */
    public function topActiveUsers(): JsonResponse
    {
        $topUsers = Cache::remember('dashboard:top_active_users', 600, function () {
            return AuditLog::select('user_id', DB::raw('COUNT(*) as activity_count'))
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
        });

        return response()->json([
            'status' => 200,
            'data' => $topUsers,
        ]);
    }

    public function zoneAlerte(): JsonResponse
    {
        $alerts = Cache::remember('dashboard:alerts', 300, function () {
            return [
                'manifeste_en_retard' => ManifesteSg::where('date_arrivee_navire', '<', now()->subDays(7))->count(),
                'fdi_sans_validation' => FdiSg::whereNull('derniere_operation')->count(),
                'declarations_sans_quittance' => DeclarationSg::whereNull('date_quittance')->count(),
            ];
        });

        return response()->json([
            'status' => 200,
            'data' => $alerts,
        ]);
    }

    public function delais(): JsonResponse
    {
        $delais = Cache::remember('dashboard:delais', 600, function () {
            $driver = DB::getDriverName();

            $fdiExpression = $driver === 'sqlite'
                ? 'AVG(JULIANDAY(date_derniere_operation) - JULIANDAY(date_fdi))'
                : 'AVG(EXTRACT(EPOCH FROM (date_derniere_operation - date_fdi)) / 86400)';

            $declarationExpression = $driver === 'sqlite'
                ? 'AVG(JULIANDAY(date_quittance) - JULIANDAY(date_declaration))'
                : 'AVG(EXTRACT(EPOCH FROM (date_quittance - date_declaration)) / 86400)';

            return [
                'delai_moyen_fdi' => FdiSg::select(DB::raw($fdiExpression.' as moyenne'))->value('moyenne'),
                'delai_moyen_declaration' => DeclarationSg::select(DB::raw($declarationExpression.' as moyenne'))->value('moyenne'),
            ];
        });

        return response()->json([
            'status' => 200,
            'data' => $delais,
        ]);
    }
}
