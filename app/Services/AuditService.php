<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log a generic action
     */
    public static function log(string $action, string $description, ?string $modelType = null, ?int $modelId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            logger()->error('Audit logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Log a login attempt
     */
    public static function logLogin(string $email, bool $success = true): void
    {
        $description = $success
            ? "User {$email} logged in successfully"
            : "Failed login attempt for {$email}";

        self::log('login', $description);
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
    public static function logCreate(string $modelType, int $modelId, array $newValues): void
    {
        $description = "Created {$modelType} #{$modelId}";
        self::log('create', $description, $modelType, $modelId, null, $newValues);
    }

    /**
     * Log an update action
     */
    public static function logUpdate(string $modelType, int $modelId, array $oldValues, array $newValues): void
    {
        $description = "Updated {$modelType} #{$modelId}";
        self::log('update', $description, $modelType, $modelId, $oldValues, $newValues);
    }

    /**
     * Log a delete action
     */
    public static function logDelete(string $modelType, int $modelId, array $oldValues): void
    {
        $description = "Deleted {$modelType} #{$modelId}";
        self::log('delete', $description, $modelType, $modelId, $oldValues, null);
    }

    /**
     * Log role assignment
     */
    public static function logRoleAssignment(int $userId, array $roles): void
    {
        $roleNames = implode(', ', $roles);
        $description = "Assigned roles [{$roleNames}] to user #{$userId}";
        self::log('role_assignment', $description, 'User', $userId, null, ['roles' => $roles]);
    }

    /**
     * Log permission assignment
     */
    public static function logPermissionAssignment(string $roleOrUser, int $id, array $permissions): void
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
}
