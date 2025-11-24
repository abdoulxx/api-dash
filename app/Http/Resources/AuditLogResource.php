<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $browser = $this->extractBrowser($this->user_agent);
        $os = $this->extractOS($this->user_agent);
        $browserVersion = $this->extractBrowserVersion($this->user_agent);

        // Determine user name or identifier
        $userName = null;
        if ($this->whenLoaded('user')) {
            $userName = $this->user->firstname && $this->user->lastname
                ? "{$this->user->firstname} {$this->user->lastname}"
                : ($this->user->email ?? "ID: {$this->user_id}");
        } elseif ($this->user_id) {
            // Try to find if user was soft deleted
            $deletedUser = \App\Models\User::withTrashed()->find($this->user_id);
            if ($deletedUser) {
                $userName = $deletedUser->firstname && $deletedUser->lastname
                    ? "{$deletedUser->firstname} {$deletedUser->lastname} (supprimé)"
                    : ($deletedUser->email ?? "ID: {$this->user_id} (supprimé)");
            } else {
                $userName = "ID: {$this->user_id} (n'existe plus)";
            }
        } else {
            $userName = "Système";
        }
        
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'user_id' => $this->user_id, // Always include user_id
            'user_name' => $userName,
            'user_email' => $this->whenLoaded('user', function () {
                return $this->user?->email;
            }),
            'action' => $this->action,
            'action_label' => $this->getActionLabel($this->action),
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'description' => $this->description,
            'description_parts' => $this->parseDescription($this->description),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'ip' => $this->ip_address, // Alias for UI
            'user_agent' => $this->user_agent,
            'browser' => $browser,
            'browser_full' => $browserVersion ? "{$browser} {$browserVersion}" : $browser,
            'navigateur' => $browserVersion ? "{$browser} {$browserVersion}" : $browser, // French label
            'operating_system' => $os,
            'systeme_exploitation' => $os, // French label
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'formatted_date' => $this->created_at?->format('l, d F Y'),
            'formatted_date_fr' => $this->getFormattedDateFr(),
            'date_group' => $this->created_at?->format('Y-m-d'), // For grouping by date
            'time' => $this->created_at?->format('H:i:s'),
        ];
    }

    /**
     * Get formatted date in French
     */
    private function getFormattedDateFr(): ?string
    {
        if (!$this->created_at) {
            return null;
        }

        try {
            // Ensure we have a valid Carbon instance
            if ($this->created_at instanceof \Carbon\Carbon) {
                $date = $this->created_at;
            } else {
                // Try to parse the date
                $parsed = Carbon::parse($this->created_at);
                // Carbon::parse can return false on failure in some cases
                if ($parsed === false || !($parsed instanceof \Carbon\Carbon)) {
                    return null;
                }
                $date = $parsed;
            }
            
            // Double check we have a valid Carbon instance
            if (!($date instanceof \Carbon\Carbon)) {
                return null;
            }

            // Set locale and format
            $date->setLocale('fr');
            return $date->isoFormat('dddd, D MMMM YYYY');
        } catch (\Exception $e) {
            // Log the error for debugging but return null
            \Log::warning('Error formatting date in AuditLogResource: ' . $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            \Log::warning('Error formatting date in AuditLogResource: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse description to extract additional information
     */
    private function parseDescription(?string $description): array
    {
        if (!$description) {
            return [];
        }

        $parts = [];
        
        // Extract quoted parts (like 'Impression d'un document FDI')
        if (preg_match("/'([^']+)'/", $description, $matches)) {
            $parts['quoted'] = $matches[1];
        }
        
        // Extract role information
        if (preg_match("/En tant qu'([^']+)/", $description, $matches)) {
            $parts['role'] = $matches[1];
        }
        
        return $parts;
    }

    /**
     * Get action label in French
     */
    private function getActionLabel(string $action): string
    {
        $labels = [
            'connexion d\'un utilisateur' => 'Connexion d\'un utilisateur',
            'login_failed' => 'Tentative de connexion échouée',
            'logout' => 'Déconnexion',
            'create' => 'Création',
            'update' => 'Modification',
            'delete' => 'Suppression',
            'Recherche' => 'Recherche',
            'Exporter' => 'Export',
            'Impression d\'un document' => 'Impression d\'un document',
            'Chat bot Messages' => 'Chat bot Messages',
            'role_assignment' => 'Assignation de rôle',
            'permission_assignment' => 'Assignation de permission',
            'access' => 'Accès',
        ];
        
        return $labels[$action] ?? $action;
    }

    /**
     * Extract browser version from user agent
     */
    private function extractBrowserVersion(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        // Chrome version
        if (preg_match('/Chrome\/(\d+)/', $userAgent, $matches)) {
            return $matches[1];
        }
        
        // Firefox version
        if (preg_match('/Firefox\/(\d+)/', $userAgent, $matches)) {
            return $matches[1];
        }
        
        // Safari version
        if (preg_match('/Version\/(\d+)/', $userAgent, $matches)) {
            return $matches[1];
        }
        
        // Edge version
        if (preg_match('/Edge\/(\d+)/', $userAgent, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Extract browser name from user agent
     */
    private function extractBrowser(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge';
        }

        return 'Unknown';
    }

    /**
     * Extract operating system from user agent
     */
    private function extractOS(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (strpos($userAgent, 'Windows') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            return 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            return 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false) {
            return 'iOS';
        }

        return 'Unknown';
    }
}
