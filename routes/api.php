<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;

// Module 1: Manifestes
use App\Http\Controllers\Api\ManifesteSgController;
use App\Http\Controllers\Api\ManifesteTtController;
use App\Http\Controllers\Api\ManifesteTcController;

// Module 2: FDI
use App\Http\Controllers\Api\FdiSgController;
use App\Http\Controllers\Api\FdiArticleController;
use App\Http\Controllers\Api\FdiRechCompController;

// Module 3: FCVR
use App\Http\Controllers\Api\FcvrSgController;
use App\Http\Controllers\Api\FcvrArticleController;
use App\Http\Controllers\Api\FcvrSgComp1Controller;
use App\Http\Controllers\Api\FcvrSgComp2Controller;

// Module 4: Déclarations
use App\Http\Controllers\Api\DeclarationSgController;
use App\Http\Controllers\Api\DeclarationArticleController;
use App\Http\Controllers\Api\DeclarationTcController;

// Module 5: Banque
use App\Http\Controllers\Api\BanqueController;
use App\Http\Controllers\Api\BanqueSadController;
use App\Http\Controllers\Api\BanqueTvfController;
use App\Http\Controllers\Api\BanqueTvfComp1Controller;
use App\Http\Controllers\Api\BanqueTvfComp2Controller;

// Module 6: Bons Provisoires
use App\Http\Controllers\Api\BonProvisoireSgController;
use App\Http\Controllers\Api\BonProvisoireArticleController;

// Module 7: Contrôles
use App\Http\Controllers\Api\ControleController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\RechercheController;
use App\Http\Controllers\Api\CacheController;

// Module 7: Utilisateurs S360 (Legacy)
use App\Http\Controllers\Api\S360v02UserController;
use App\Http\Controllers\Api\S360v02UggroupController;
use App\Http\Controllers\Api\S360v02UgmemberController;
use App\Http\Controllers\Api\S360v02UgrightController;

// Module 8: SYDAM Auto
use App\Http\Controllers\Api\SydamAutoController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Dashboard routes
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
        Route::get('/user-growth', [DashboardController::class, 'userGrowth']);
        Route::get('/action-stats', [DashboardController::class, 'actionStats']);
        Route::get('/top-active-users', [DashboardController::class, 'topActiveUsers']);
        Route::get('/alerts', [DashboardController::class, 'zoneAlerte']);
        Route::get('/delais', [DashboardController::class, 'delais']);
        Route::get('/delai', [DashboardController::class, 'delais']); // Alias pour compatibilité
    });

    // Import / Export
    Route::prefix('export')->group(function () {
        Route::post('excel', [ExportController::class, 'exportExcel']);
        Route::post('pdf', [ExportController::class, 'exportPdf']);
        Route::post('xml', [ExportController::class, 'exportXml']);
        Route::get('templates', [ExportController::class, 'templates']);
    });

    Route::prefix('import')->group(function () {
        Route::post('excel', [ImportController::class, 'importExcel']);
        Route::post('csv', [ImportController::class, 'importCsv']);
    });

    // Recherche avancée
    Route::prefix('recherche')->group(function () {
        // Recherche simple
        Route::get('manifestes', [RechercheController::class, 'rechercheManifeste']);
        Route::get('fdi', [RechercheController::class, 'rechercheFdi']);
        Route::get('declarations', [RechercheController::class, 'rechercheDeclaration']);
        Route::get('banque', [RechercheController::class, 'rechercheBanque']);
        
        // Recherche avancée unifiée
        Route::prefix('avancee')->group(function () {
            Route::post('/', [RechercheController::class, 'rechercheAvancee']);
            Route::get('/options', [RechercheController::class, 'getSearchOptions']);
            Route::get('/flow-diagram', [RechercheController::class, 'getFlowDiagram']);
            Route::get('/navigate', [RechercheController::class, 'navigate']);
            Route::get('/node/{type}/{identifier}', [RechercheController::class, 'getNodeDetails'])->where('identifier', '.*');
            Route::get('/{option}/results', [RechercheController::class, 'getResultsByTab']);
            
            // Export des résultats de recherche
            Route::post('/export/excel', [RechercheController::class, 'exportExcel']);
            Route::post('/export/pdf', [RechercheController::class, 'exportPdf']);
            Route::post('/export/xml', [RechercheController::class, 'exportXml']);
        });
    });

    // User management routes
    // IMPORTANT: Les routes spécifiques doivent être définies AVANT apiResource
    // pour éviter que Laravel ne capture {user}/photo comme {user}
    Route::get('users/options/departments', [UserController::class, 'getDepartments']);
    Route::get('users/options/managers', [UserController::class, 'getManagers']);
    Route::get('users/options/fonctions', [UserController::class, 'getFonctions']);
    Route::get('users/options/statuts', [UserController::class, 'getStatuts']);
    Route::get('users/trashed/list', [UserController::class, 'trashed']);
    
    // Routes avec paramètre {id} - doivent être avant apiResource
    // Utiliser {id} au lieu de {user} pour éviter le route model binding automatique
    // Désactiver le route model binding pour ces routes en utilisant ->where()
    Route::get('users/{id}/activity-history', [UserController::class, 'activityHistory'])->where('id', '[0-9A-Za-z]{26}');
    Route::get('users/{id}/statistics', [UserController::class, 'getStatistics'])->where('id', '[0-9A-Za-z]{26}');
    Route::get('users/{id}/photo', [UserController::class, 'getPhoto'])->where('id', '[0-9A-Za-z]{26}');
    Route::post('users/{id}/photo', [UserController::class, 'uploadPhoto'])->where('id', '[0-9A-Za-z]{26}');
    Route::delete('users/{id}/photo', [UserController::class, 'deletePhoto'])->where('id', '[0-9A-Za-z]{26}');
    Route::post('users/{id}/restore', [UserController::class, 'restore'])->where('id', '[0-9A-Za-z]{26}');
    Route::delete('users/{id}/force', [UserController::class, 'forceDelete'])->where('id', '[0-9A-Za-z]{26}');
    
    // apiResource doit être en dernier
    Route::apiResource('users', UserController::class);

    // Admin management routes
    // IMPORTANT: Les routes spécifiques doivent être définies AVANT apiResource
    Route::get('admins/trashed/list', [AdminController::class, 'trashed']);
    
    // Routes avec paramètre {id} - doivent être avant apiResource
    // Utiliser {id} au lieu de {admin} pour éviter le route model binding automatique
    // Désactiver le route model binding pour ces routes en utilisant ->where()
    Route::get('admins/{id}/photo', [AdminController::class, 'getPhoto'])->where('id', '[0-9A-Za-z]{26}');
    Route::post('admins/{id}/photo', [AdminController::class, 'uploadPhoto'])->where('id', '[0-9A-Za-z]{26}');
    Route::delete('admins/{id}/photo', [AdminController::class, 'deletePhoto'])->where('id', '[0-9A-Za-z]{26}');
    Route::post('admins/{id}/restore', [AdminController::class, 'restore'])->where('id', '[0-9A-Za-z]{26}');
    Route::delete('admins/{id}/force', [AdminController::class, 'forceDelete'])->where('id', '[0-9A-Za-z]{26}');
    
    // apiResource doit être en dernier
    Route::apiResource('admins', AdminController::class);

    // Role management routes
    Route::apiResource('roles', RoleController::class);
    Route::get('roles/options/list', [RoleController::class, 'getOptions']);
    // Soft delete management routes for roles
    Route::get('roles/trashed/list', [RoleController::class, 'trashed']);
    Route::post('roles/{role}/restore', [RoleController::class, 'restore']);
    Route::delete('roles/{role}/force', [RoleController::class, 'forceDelete']);

    // Permission management routes
    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::get('/hierarchical', [PermissionController::class, 'getHierarchical']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::get('/{id}', [PermissionController::class, 'show']);
        Route::put('/{id}', [PermissionController::class, 'update']);
        Route::patch('/{id}', [PermissionController::class, 'update']);
        Route::delete('/{id}', [PermissionController::class, 'destroy']);

        // Assign permissions to role
        Route::post('/roles/{roleId}/assign', [PermissionController::class, 'assignToRole']);
        Route::get('/roles/{roleId}', [PermissionController::class, 'getRolePermissions']);

        // Assign permissions to user
        Route::post('/users/{userId}/assign', [PermissionController::class, 'assignToUser']);
        Route::get('/users/{userId}', [PermissionController::class, 'getUserPermissions']);

        // Soft delete management routes for permissions
        Route::get('/trashed/list', [PermissionController::class, 'trashed']);
        Route::post('/{id}/restore', [PermissionController::class, 'restore']);
        Route::delete('/{id}/force', [PermissionController::class, 'forceDelete']);
    });

    // Audit logs routes
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/action-types', [AuditLogController::class, 'getActionTypes']);
        Route::get('/{id}', [AuditLogController::class, 'show']);
        Route::get('/user/{userId}', [AuditLogController::class, 'userLogs']);
        Route::get('/model/{modelType}/{modelId}', [AuditLogController::class, 'modelLogs']);
    });

    // Cache management routes
    Route::prefix('cache')->group(function () {
        Route::get('/', [CacheController::class, 'index']);
        Route::get('/stats', [CacheController::class, 'stats']);
        Route::get('/debug/keys', [CacheController::class, 'debugKeys']);
        Route::get('/tag/{tag}', [CacheController::class, 'byTag']);
        Route::get('/{key}', [CacheController::class, 'show'])->where('key', '.*');
        Route::delete('/{key}', [CacheController::class, 'destroy'])->where('key', '.*');
        Route::delete('/tag/{tag}/flush', [CacheController::class, 'flushTag']);
        Route::delete('/flush', [CacheController::class, 'flush']);
    });

    // ============================================
    // MODULE 1 : GESTION DES MANIFESTES
    // ============================================
    Route::prefix('manifestes')->group(function () {
        // Routes apiResource pour les manifestes
        Route::apiResource('sg', ManifesteSgController::class);
        Route::apiResource('tt', ManifesteTtController::class);
        Route::apiResource('tc', ManifesteTcController::class);

        // Endpoints supplémentaires pour ManifesteSg (doivent être après apiResource)
        Route::get('sg/{manifesteId}/titres-transport', [ManifesteSgController::class, 'titresTransport']);
        Route::get('sg/{manifesteId}/conteneurs', [ManifesteSgController::class, 'conteneurs']);
        Route::get('sg/{manifesteId}/declarations', [ManifesteSgController::class, 'declarations']);
        Route::post('sg/{manifesteId}/validate', [ManifesteSgController::class, 'validate']);
    });

    // ============================================
    // MODULE 2 : GESTION FDI (Fiche de Dédouanement)
    // ============================================
    Route::prefix('fdi')->group(function () {
        // Routes apiResource pour les FDI
        Route::apiResource('sg', FdiSgController::class)->parameters(['sg' => 'fdi_sg']);
        Route::apiResource('articles', FdiArticleController::class);
        Route::apiResource('rech-comp', FdiRechCompController::class);

        // Endpoints supplémentaires pour FdiSg (doivent être après apiResource)
        Route::get('sg/{fdiSg}/articles', [FdiSgController::class, 'articles']);
        Route::get('sg/{fdiSg}/fcvr', [FdiSgController::class, 'fcvr']);
        Route::get('sg/{fdiSg}/declarations', [FdiSgController::class, 'declarations']);
        Route::post('sg/{fdiSg}/compare', [FdiSgController::class, 'compare']);
        Route::post('sg/{fdiSg}/validate', [FdiSgController::class, 'validateFdi']);
        Route::get('sg/{fdiSg}/validate/result', [FdiSgController::class, 'validateResult']);
        Route::post('sg/{fdiSg}/calculate-droits', [FdiSgController::class, 'calculateDroits']);
        Route::post('sg/{ulid}/restore', [FdiSgController::class, 'restore'])->where('ulid', '[0-9A-Za-z]{26}');
    });

    // ============================================
    // MODULE 3 : GESTION FCVR (Fiche de Contrôle)
    // ============================================
    Route::prefix('fcvr')->group(function () {
        // Routes apiResource pour les FCVR
        Route::apiResource('sg', FcvrSgController::class);
        Route::apiResource('articles', FcvrArticleController::class);
        Route::apiResource('comp-1', FcvrSgComp1Controller::class);
        Route::apiResource('comp-2', FcvrSgComp2Controller::class);

        // Endpoints supplémentaires pour FcvrSg (doivent être après apiResource)
        Route::get('sg/{fcvrSg}/articles', [FcvrSgController::class, 'articles']);
        Route::get('sg/{fcvrSg}/fdi', [FcvrSgController::class, 'fdi']);
        Route::get('sg/{fcvrSg}/declaration', [FcvrSgController::class, 'declaration']);
        Route::post('sg/{fcvrSg}/compare', [FcvrSgController::class, 'compare']);
        Route::post('sg/{fcvrSg}/validate', [FcvrSgController::class, 'validate']);
    });

    // ============================================
    // MODULE 4 : GESTION DES DÉCLARATIONS
    // ============================================
    Route::prefix('declarations')->group(function () {
        // Routes pour DeclarationSg avec ULID (définies manuellement pour utiliser ULID au lieu de route model binding)
        // IMPORTANT: Les routes spécifiques doivent être définies AVANT les routes avec paramètres dynamiques
        Route::get('sg', [DeclarationSgController::class, 'index']);
        Route::post('sg', [DeclarationSgController::class, 'store']);
        Route::get('sg/calculate-taxes/result', [DeclarationSgController::class, 'calculateTaxesResult']);
        
        // Routes avec paramètre {ulid} - doivent être après les routes spécifiques
        Route::get('sg/{ulid}', [DeclarationSgController::class, 'show'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::put('sg/{ulid}', [DeclarationSgController::class, 'update'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::patch('sg/{ulid}', [DeclarationSgController::class, 'update'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::delete('sg/{ulid}', [DeclarationSgController::class, 'destroy'])->where('ulid', '[0-9A-Za-z]{26}');
        
        // Endpoints supplémentaires pour DeclarationSg avec {ulid}
        Route::get('sg/{ulid}/manifeste', [DeclarationSgController::class, 'manifeste'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::get('sg/{ulid}/articles', [DeclarationSgController::class, 'articles'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::get('sg/{ulid}/conteneurs', [DeclarationSgController::class, 'conteneurs'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::post('sg/{ulid}/validate', [DeclarationSgController::class, 'validate'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::post('sg/{ulid}/calculate-taxes', [DeclarationSgController::class, 'calculateTaxes'])->where('ulid', '[0-9A-Za-z]{26}');
        
        // Routes apiResource pour les autres ressources
        Route::apiResource('articles', DeclarationArticleController::class);
        Route::apiResource('tc', DeclarationTcController::class);
    });

    // ============================================
    // MODULE 5 : GESTION BANQUE
    // ============================================
    Route::prefix('banque')->group(function () {
        // Routes pour la table principale BANQUE (doivent être avant les routes génériques)
        Route::get('/trashed', [BanqueController::class, 'trashed']);
        Route::post('/', [BanqueController::class, 'store']);
        Route::get('/', [BanqueController::class, 'index']);
        Route::get('/{id}', [BanqueController::class, 'show'])->where('id', '[0-9A-Za-z]{26}');
        Route::put('/{id}', [BanqueController::class, 'update'])->where('id', '[0-9A-Za-z]{26}');
        Route::patch('/{id}', [BanqueController::class, 'update'])->where('id', '[0-9A-Za-z]{26}');
        Route::delete('/{id}', [BanqueController::class, 'destroy'])->where('id', '[0-9A-Za-z]{26}');
        Route::post('/{id}/restore', [BanqueController::class, 'restore'])->where('id', '[0-9A-Za-z]{26}');
        Route::delete('/{id}/force', [BanqueController::class, 'forceDelete'])->where('id', '[0-9A-Za-z]{26}');
        
        // Routes apiResource pour les banques
        Route::post('tvf-comp-1/{tvf_comp_1}/restore', [BanqueTvfComp1Controller::class, 'restore']);
        Route::apiResource('tvf-comp-1', BanqueTvfComp1Controller::class);
        Route::post('tvf-comp-2/{identifier}/restore', [BanqueTvfComp2Controller::class, 'restore'])->where('identifier', '[0-9A-Za-z]{26}|[0-9]+|.+');
        Route::apiResource('tvf-comp-2', BanqueTvfComp2Controller::class);

        // Routes SAD avec ULID (définies manuellement pour utiliser ULID au lieu de route model binding)
        Route::get('sad', [BanqueSadController::class, 'index']);
        Route::post('sad', [BanqueSadController::class, 'store']);
        Route::get('sad/trashed', [BanqueSadController::class, 'trashed']);
        Route::get('sad/{ulid}', [BanqueSadController::class, 'show'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::put('sad/{ulid}', [BanqueSadController::class, 'update'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::patch('sad/{ulid}', [BanqueSadController::class, 'update'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::delete('sad/{ulid}', [BanqueSadController::class, 'destroy'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::post('sad/{ulid}/restore', [BanqueSadController::class, 'restore'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::delete('sad/{ulid}/force', [BanqueSadController::class, 'forceDelete'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::get('sad/{ulid}/declaration', [BanqueSadController::class, 'declaration'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::get('sad/{ulid}/manifeste', [BanqueSadController::class, 'manifeste'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::match(['get', 'post'], 'sad/{ulid}/validate', [BanqueSadController::class, 'validate'])->where('ulid', '[0-9A-Za-z]{26}');

        // Routes TVF avec ULID (définies manuellement pour utiliser ULID au lieu de route model binding)
        Route::get('tvf', [BanqueTvfController::class, 'index']);
        Route::post('tvf', [BanqueTvfController::class, 'store']);
        Route::get('tvf/trashed', [BanqueTvfController::class, 'trashed']);
        Route::get('tvf/{ulid}', [BanqueTvfController::class, 'show'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::put('tvf/{ulid}', [BanqueTvfController::class, 'update'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::patch('tvf/{ulid}', [BanqueTvfController::class, 'update'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::delete('tvf/{ulid}', [BanqueTvfController::class, 'destroy'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::post('tvf/{ulid}/restore', [BanqueTvfController::class, 'restore'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::delete('tvf/{ulid}/force', [BanqueTvfController::class, 'forceDelete'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::get('tvf/{ulid}/fdi', [BanqueTvfController::class, 'fdi'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::get('tvf/{ulid}/comparaisons', [BanqueTvfController::class, 'comparaisons'])->where('ulid', '[0-9A-Za-z]{26}');
        Route::match(['get', 'post'], 'tvf/{ulid}/validate', [BanqueTvfController::class, 'validate'])->where('ulid', '[0-9A-Za-z]{26}');
    });

    // ============================================
    // MODULE 8 : GESTION SYDAM AUTO
    // ============================================
    Route::prefix('sydam-auto')->group(function () {
        Route::get('/', [SydamAutoController::class, 'index']);
        Route::get('/{id}', [SydamAutoController::class, 'show']);
    });

    // ============================================
    // MODULE 6 : GESTION BONS PROVISOIRES
    // ============================================
    Route::prefix('bons-provisoires')->group(function () {
        // Routes apiResource pour les bons provisoires
        Route::apiResource('sg', BonProvisoireSgController::class);
        Route::apiResource('articles', BonProvisoireArticleController::class);

        // Endpoints supplémentaires pour BonProvisoireSg (doivent être après apiResource)
        Route::get('sg/{id}/articles', [BonProvisoireSgController::class, 'articles'])->where('id', '[0-9A-Za-z]{26}');
        Route::post('sg/{id}/validate', [BonProvisoireSgController::class, 'validate'])->where('id', '[0-9A-Za-z]{26}');
        Route::get('sg/validate/result', [BonProvisoireSgController::class, 'validateResult']);
        Route::post('sg/{id}/expire', [BonProvisoireSgController::class, 'expire'])->where('id', '[0-9A-Za-z]{26}');
    });

    // ============================================
    // MODULE 7 : CONTRÔLES ET COMPARAISONS
    // ============================================
    Route::prefix('controle')->group(function () {
        Route::get('fdi/{primary}/{secondary}', [ControleController::class, 'compareFdi'])
            ->where('primary', '[0-9A-Za-z]{26}')
            ->where('secondary', '[0-9A-Za-z]{26}');
        Route::get('fcvr/{fcvr}/{declaration}', [ControleController::class, 'compareFcvr'])
            ->where('fcvr', '[0-9A-Za-z]{26}')
            ->where('declaration', '[0-9A-Za-z]{26}');
        Route::get('manifeste/{manifeste}/{declaration}', [ControleController::class, 'compareManifeste'])
            ->where('manifeste', '[0-9A-Za-z]{26}')
            ->where('declaration', '[0-9A-Za-z]{26}');
        Route::get('banque/{banqueSad}/{declaration}', [ControleController::class, 'compareBanque'])
            ->where('banqueSad', '[0-9A-Za-z]{26}')
            ->where('declaration', '[0-9A-Za-z]{26}');
        Route::post('dispatch', [ControleController::class, 'dispatch']);
        Route::get('result', [ControleController::class, 'result']);
    });

    // ============================================
    // MODULE 7 : GESTION UTILISATEURS S360 (Legacy)
    // ============================================
    Route::prefix('s360')->group(function () {
        // Routes apiResource pour les utilisateurs legacy
        Route::apiResource('users', S360v02UserController::class);
        Route::apiResource('groups', S360v02UggroupController::class);
        Route::apiResource('members', S360v02UgmemberController::class);
        Route::apiResource('rights', S360v02UgrightController::class);

        // Endpoints supplémentaires (doivent être après apiResource)
        Route::get('users/{s360v02User}/groups', [S360v02UserController::class, 'groups']);
        Route::get('users/{s360v02User}/rights', [S360v02UserController::class, 'rights']);
        Route::get('groups/{s360v02Uggroup}/members', [S360v02UggroupController::class, 'members']);
        Route::get('groups/{s360v02Uggroup}/rights', [S360v02UggroupController::class, 'rights']);
    });
});
