<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log the request after it has been processed
        if (Auth::check() && $this->shouldLog($request)) {
            $this->logAudit($request, $response);
        }

        return $response;
    }

    /**
     * Determine if the request should be logged
     */
    protected function shouldLog(Request $request): bool
    {
        // Log only specific HTTP methods
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    /**
     * Log the audit trail
     */
    protected function logAudit(Request $request, Response $response): void
    {
        try {
            $action = $this->determineAction($request);
            $description = $this->generateDescription($request, $action);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the application
            logger()->error('Audit logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Determine the action being performed
     */
    protected function determineAction(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();

        if (str_contains($path, 'login')) {
            return 'login';
        }

        if (str_contains($path, 'logout')) {
            return 'logout';
        }

        return match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'access',
        };
    }

    /**
     * Generate a description for the audit log
     */
    protected function generateDescription(Request $request, string $action): string
    {
        $user = Auth::user();
        $path = $request->path();
        $resource = $this->extractResource($path);

        return "{$user->name} performed {$action} on {$resource}";
    }

    /**
     * Extract the resource being acted upon from the path
     */
    protected function extractResource(string $path): string
    {
        $parts = explode('/', $path);
        return $parts[count($parts) - 1] ?? 'unknown';
    }
}
