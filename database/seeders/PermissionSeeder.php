<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Based on s360_analyse admin_rights structure
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions in hierarchical structure
        // Format: module/page/action
        $permissions = [
            // Accueil (Home)
            'Accueil' => [
                'Page Accueil' => ['Rechercher', 'Exporter'],
            ],
            
            // Controle S360°
            'Controle S360°' => [
                'Page pour les Controles' => ['Ajouter', 'Exporter', 'Rechercher', 'Supprimer'],
            ],
            
            // Alertes
            'Alertes' => [
                'Page Alertes' => ['Rechercher', 'Exporter'],
            ],
            
            // Documents
            'Documents' => [
                'Page Documents' => ['Ajouter', 'Exporter', 'Rechercher', 'Télécharger', 'Supprimer'],
            ],
            
            // Manifestes
            'Manifestes' => [
                'Manifeste SG' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter', 'Imprimer'],
                'Manifeste TT' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter', 'Imprimer'],
                'Manifeste TC' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter', 'Imprimer'],
            ],
            
            // FDI
            'FDI' => [
                'FDI SG' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter', 'Imprimer'],
                'FDI Article' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter'],
            ],
            
            // FCVR
            'FCVR' => [
                'FCVR SG' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter', 'Imprimer'],
                'FCVR Article' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter'],
            ],
            
            // Déclarations
            'Déclarations' => [
                'Déclaration SG' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter', 'Imprimer'],
                'Déclaration Article' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter'],
            ],
            
            // Banque
            'Banque' => [
                'Banque SAD' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter'],
                'Banque TVF' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter'],
            ],
            
            // Bons Provisoires
            'Bons Provisoires' => [
                'Bon Provisoire SG' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter'],
                'Bon Provisoire Article' => ['Ajouter', 'Modifier', 'Supprimer', 'Rechercher', 'Exporter'],
            ],
            
            // Administration
            'Administration' => [
                'Utilisateurs' => ['Créer', 'Modifier', 'Supprimer', 'Rechercher', 'Voir'],
                'Rôles' => ['Créer', 'Modifier', 'Supprimer', 'Rechercher', 'Voir'],
                'Permissions' => ['Assigner', 'Retirer', 'Voir'],
            ],
        ];

        // Create permissions
        $createdPermissions = [];
        foreach ($permissions as $module => $pages) {
            foreach ($pages as $page => $actions) {
                foreach ($actions as $action) {
                    $permissionName = "{$module}/{$page}/{$action}";
                    $permission = Permission::firstOrCreate(
                        ['name' => $permissionName, 'guard_name' => 'web'],
                        ['name' => $permissionName, 'guard_name' => 'web']
                    );
                    $createdPermissions[$module][$page][] = $permission;
                }
            }
        }

        // Create default roles
        $roles = [
            'Administrateur' => [
                'description' => 'Facilite et sécurise les échanges commerciaux',
                'permissions' => Permission::all()->pluck('name')->toArray(),
            ],
            'Première ligne' => [
                'description' => 'Simplifier l\'accès aux fonctionnalités',
                'permissions' => [
                    'Accueil/Page Accueil/Rechercher',
                    'Accueil/Page Accueil/Exporter',
                    'Controle S360°/Page pour les Controles/Rechercher',
                    'Alertes/Page Alertes/Rechercher',
                    'Documents/Page Documents/Rechercher',
                    'Documents/Page Documents/Télécharger',
                ],
            ],
            'Douanes (DED)' => [
                'description' => 'Gère les procédures douanières',
                'permissions' => [
                    'Manifestes/Manifeste SG/Rechercher',
                    'Manifestes/Manifeste SG/Voir',
                    'FDI/FDI SG/Rechercher',
                    'FDI/FDI SG/Voir',
                    'FCVR/FCVR SG/Rechercher',
                    'FCVR/FCVR SG/Voir',
                    'Déclarations/Déclaration SG/Rechercher',
                    'Déclarations/Déclaration SG/Voir',
                ],
            ],
            'Analyste' => [
                'description' => 'Analyse et contrôle des données',
                'permissions' => [
                    'Accueil/Page Accueil/Rechercher',
                    'Accueil/Page Accueil/Exporter',
                    'Controle S360°/Page pour les Controles/Rechercher',
                    'Controle S360°/Page pour les Controles/Exporter',
                    'Alertes/Page Alertes/Rechercher',
                    'Alertes/Page Alertes/Exporter',
                    'Documents/Page Documents/Rechercher',
                    'Documents/Page Documents/Télécharger',
                ],
            ],
            'Collecteur' => [
                'description' => 'Collecte et saisie des données',
                'permissions' => [
                    'Manifestes/Manifeste SG/Ajouter',
                    'Manifestes/Manifeste SG/Modifier',
                    'Manifestes/Manifeste SG/Rechercher',
                    'FDI/FDI SG/Ajouter',
                    'FDI/FDI SG/Modifier',
                    'FDI/FDI SG/Rechercher',
                    'FCVR/FCVR SG/Ajouter',
                    'FCVR/FCVR SG/Modifier',
                    'FCVR/FCVR SG/Rechercher',
                ],
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'description' => $roleData['description'] ?? null,
                ]
            );

            // Assign permissions to role
            $permissionNames = $roleData['permissions'];
            $permissionsToAssign = Permission::whereIn('name', $permissionNames)->get();
            $role->syncPermissions($permissionsToAssign);
        }

        $this->command->info('Permissions and roles seeded successfully!');
    }
}
