<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("Peuplement de la table roles...");

        // Vérifier si la table s360v02_uggroups existe et contient des données
        if (DB::getSchemaBuilder()->hasTable('s360v02_uggroups')) {
            $legacyGroups = DB::table('s360v02_uggroups')->get();
            
            if ($legacyGroups->isNotEmpty()) {
                $this->command->info("Trouvé {$legacyGroups->count()} groupe(s) dans s360v02_uggroups");
                $this->importFromLegacyTable($legacyGroups);
                return;
            }
        }

        // Sinon, créer des rôles supplémentaires basés sur les besoins du système
        $this->command->info("Aucune donnée trouvée dans s360v02_uggroups. Création de rôles supplémentaires...");
        $this->createAdditionalRoles();
    }

    /**
     * Importer les rôles depuis la table s360v02_uggroups
     */
    private function importFromLegacyTable($legacyGroups): void
    {
        $count = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($legacyGroups as $legacyGroup) {
            try {
                $groupName = $legacyGroup->Label ?? $legacyGroup->label ?? "Groupe {$legacyGroup->GroupID}";
                
                // Vérifier si le rôle existe déjà
                $existingRole = Role::where('name', $groupName)->first();
                
                if ($existingRole) {
                    $skipped++;
                    continue;
                }

                // Créer le rôle
                $role = Role::create([
                    'name' => $groupName,
                    'description' => "Rôle importé depuis s360v02_uggroups (ID: {$legacyGroup->GroupID})",
                    'guard_name' => 'web',
                ]);

                $count++;
                
                if ($count % 10 === 0) {
                    $this->command->info("Importé {$count} rôle(s)...");
                }
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 5) {
                    $this->command->warn("Erreur lors de l'importation du groupe ID {$legacyGroup->GroupID}: " . $e->getMessage());
                }
            }
        }

        $this->command->info("✅ {$count} rôle(s) importé(s) avec succès");
        if ($skipped > 0) {
            $this->command->info("⚠️  {$skipped} rôle(s) déjà existant(s) (ignoré(s))");
        }
        if ($errors > 0) {
            $this->command->warn("⚠️  {$errors} erreur(s) rencontrée(s)");
        }
    }

    /**
     * Créer des rôles supplémentaires basés sur les besoins du système
     */
    private function createAdditionalRoles(): void
    {
        $additionalRoles = [
            [
                'name' => 'Gestionnaire Contrôle',
                'description' => 'Gère les contrôles et l\'accueil',
                'permissions' => [
                    'Accueil/Page Accueil/Rechercher',
                    'Accueil/Page Accueil/Exporter',
                    'Controle S360°/Page pour les Controles/Ajouter',
                    'Controle S360°/Page pour les Controles/Exporter',
                    'Controle S360°/Page pour les Controles/Rechercher',
                    'Controle S360°/Page pour les Controles/Supprimer',
                ],
            ],
            [
                'name' => 'Gestionnaire Manifestes',
                'description' => 'Gère tous les types de manifestes',
                'permissions' => [
                    'Manifestes/Manifeste SG/Ajouter',
                    'Manifestes/Manifeste SG/Modifier',
                    'Manifestes/Manifeste SG/Supprimer',
                    'Manifestes/Manifeste SG/Rechercher',
                    'Manifestes/Manifeste SG/Exporter',
                    'Manifestes/Manifeste SG/Imprimer',
                    'Manifestes/Manifeste TT/Ajouter',
                    'Manifestes/Manifeste TT/Modifier',
                    'Manifestes/Manifeste TT/Rechercher',
                    'Manifestes/Manifeste TC/Ajouter',
                    'Manifestes/Manifeste TC/Modifier',
                    'Manifestes/Manifeste TC/Rechercher',
                ],
            ],
            [
                'name' => 'Gestionnaire FDI/FCVR',
                'description' => 'Gère les FDI et FCVR',
                'permissions' => [
                    'FDI/FDI SG/Ajouter',
                    'FDI/FDI SG/Modifier',
                    'FDI/FDI SG/Supprimer',
                    'FDI/FDI SG/Rechercher',
                    'FDI/FDI SG/Exporter',
                    'FDI/FDI SG/Valider',
                    'FCVR/FCVR SG/Ajouter',
                    'FCVR/FCVR SG/Modifier',
                    'FCVR/FCVR SG/Supprimer',
                    'FCVR/FCVR SG/Rechercher',
                    'FCVR/FCVR SG/Exporter',
                    'FCVR/FCVR SG/Valider',
                ],
            ],
            [
                'name' => 'Gestionnaire Déclarations',
                'description' => 'Gère les déclarations douanières',
                'permissions' => [
                    'Déclarations/Déclaration SG/Ajouter',
                    'Déclarations/Déclaration SG/Modifier',
                    'Déclarations/Déclaration SG/Supprimer',
                    'Déclarations/Déclaration SG/Rechercher',
                    'Déclarations/Déclaration SG/Exporter',
                    'Déclarations/Déclaration SG/Valider',
                    'Déclarations/Déclaration SG/Calculer taxes',
                ],
            ],
            [
                'name' => 'Gestionnaire Banque',
                'description' => 'Gère les opérations bancaires (TVF/SAD)',
                'permissions' => [
                    'Banque/TVF/Rechercher',
                    'Banque/TVF/Créer',
                    'Banque/TVF/Modifier',
                    'Banque/TVF/Valider',
                    'Banque/SAD/Rechercher',
                    'Banque/SAD/Créer',
                    'Banque/SAD/Modifier',
                    'Banque/SAD/Valider',
                ],
            ],
            [
                'name' => 'Superviseur',
                'description' => 'Supervise et valide les opérations',
                'permissions' => [
                    'Manifestes/Manifeste SG/Voir',
                    'Manifestes/Manifeste SG/Valider',
                    'FDI/FDI SG/Voir',
                    'FDI/FDI SG/Valider',
                    'FCVR/FCVR SG/Voir',
                    'FCVR/FCVR SG/Valider',
                    'Déclarations/Déclaration SG/Voir',
                    'Déclarations/Déclaration SG/Valider',
                    'Banque/TVF/Voir',
                    'Banque/TVF/Valider',
                    'Banque/SAD/Voir',
                    'Banque/SAD/Valider',
                ],
            ],
            [
                'name' => 'Lecteur',
                'description' => 'Accès en lecture seule',
                'permissions' => [
                    'Accueil/Page Accueil/Rechercher',
                    'Manifestes/Manifeste SG/Rechercher',
                    'Manifestes/Manifeste SG/Voir',
                    'FDI/FDI SG/Rechercher',
                    'FDI/FDI SG/Voir',
                    'FCVR/FCVR SG/Rechercher',
                    'FCVR/FCVR SG/Voir',
                    'Déclarations/Déclaration SG/Rechercher',
                    'Déclarations/Déclaration SG/Voir',
                    'Banque/TVF/Rechercher',
                    'Banque/TVF/Voir',
                    'Banque/SAD/Rechercher',
                    'Banque/SAD/Voir',
                ],
            ],
        ];

        $count = 0;
        foreach ($additionalRoles as $roleData) {
            try {
                // Vérifier si le rôle existe déjà
                $existingRole = Role::where('name', $roleData['name'])->first();
                
                if ($existingRole) {
                    // Mettre à jour les permissions si le rôle existe
                    $permissions = Permission::whereIn('name', $roleData['permissions'])->get();
                    $existingRole->syncPermissions($permissions);
                    continue;
                }

                // Créer le rôle
                $role = Role::create([
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'guard_name' => 'web',
                ]);

                // Assigner les permissions
                if (isset($roleData['permissions']) && !empty($roleData['permissions'])) {
                    $permissions = Permission::whereIn('name', $roleData['permissions'])->get();
                    $role->syncPermissions($permissions);
                }

                $count++;
            } catch (\Exception $e) {
                $this->command->warn("Erreur lors de la création du rôle {$roleData['name']}: " . $e->getMessage());
            }
        }

        $this->command->info("✅ {$count} rôle(s) supplémentaire(s) créé(s)");
    }
}
