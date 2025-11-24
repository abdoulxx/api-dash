<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log a generic action
     */
    public static function log(string $action, string $description, ?string $modelType = null, string|int|null $modelId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => is_null($modelId) ? null : (string) $modelId,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            
            // Invalidate audit logs cache when a new log is created
            \App\Support\CacheTagger::tags(['audit-logs'])->flush();
        } catch (\Exception $e) {
            logger()->error('Audit logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Log a login attempt
     */
    public static function logLogin(string $email, bool $success = true): void
    {
        if ($success) {
            $user = \App\Models\User::where('email', $email)->first();
            $role = $user && $user->roles->isNotEmpty() 
                ? "En tant qu'{$user->roles->first()->name}" 
                : '';
            $description = "Un utilisateur s'est connecté à l'application" . ($role ? " {$role}" : '');
        } else {
            $description = "Tentative de connexion échouée pour {$email}";
        }

        self::log($success ? 'connexion d\'un utilisateur' : 'login_failed', $description);
    }

    /**
     * Log a logout
     */
    public static function logLogout(): void
    {
        $user = Auth::user();
        self::log('logout', "User {$user->email} logged out");
    }

    /**
     * Log a create action
     */
    public static function logCreate(string $modelType, string|int $modelId, array $newValues): void
    {
        $description = "Created {$modelType} #{$modelId}";
        self::log('create', $description, $modelType, $modelId, null, $newValues);
    }

    /**
     * Log an update action
     */
    public static function logUpdate(string $modelType, string|int $modelId, array $oldValues, array $newValues): void
    {
        $description = "Updated {$modelType} #{$modelId}";
        self::log('update', $description, $modelType, $modelId, $oldValues, $newValues);
    }

    /**
     * Log a delete action
     */
    public static function logDelete(string $modelType, string|int $modelId, array $oldValues): void
    {
        $description = "Deleted {$modelType} #{$modelId}";
        self::log('delete', $description, $modelType, $modelId, $oldValues, null);
    }

    /**
     * Log role assignment
     */
    public static function logRoleAssignment(string|int $userId, array $roles): void
    {
        $roleNames = implode(', ', $roles);
        $description = "Assigned roles [{$roleNames}] to user #{$userId}";
        self::log('role_assignment', $description, 'User', $userId, null, ['roles' => $roles]);
    }

    /**
     * Log permission assignment
     */
    public static function logPermissionAssignment(string $roleOrUser, string|int $id, array $permissions): void
    {
        $permissionNames = implode(', ', $permissions);
        $description = "Assigned permissions [{$permissionNames}] to {$roleOrUser} #{$id}";
        self::log('permission_assignment', $description, $roleOrUser, $id, null, ['permissions' => $permissions]);
    }

    /**
     * Log resource access
     */
    public static function logAccess(string $resource): void
    {
        $user = Auth::user();
        $description = "User {$user->email} accessed {$resource}";
        self::log('access', $description);
    }

    /**
     * Log search action
     */
    public static function logSearch(string $resource, string $query): void
    {
        $queryText = $query ? " '{$query}'" : '';
        $description = "Recherche d'une {$resource} par un utilisateur{$queryText}";
        self::log('Recherche', $description, $resource);
    }

    /**
     * Log export action
     */
    public static function logExport(string $resource, ?string $format = null): void
    {
        $formatText = $format ? " en format {$format}" : '';
        $description = "Un utilisateur a exporté {$resource}{$formatText}";
        self::log('Exporter', $description, $resource);
    }

    /**
     * Log print action
     */
    public static function logPrint(string $resource, ?string $document = null): void
    {
        $docText = $document ? " '{$document}'" : '';
        $description = "Un utilisateur à fait une impression de document{$docText}";
        self::log('Impression d\'un document', $description, $resource);
    }

    /**
     * Log chatbot message
     */
    public static function logChatbotMessage(string $message): void
    {
        $description = "Message chatbot : {$message}";
        self::log('Chat bot Messages', $description);
    }
}
