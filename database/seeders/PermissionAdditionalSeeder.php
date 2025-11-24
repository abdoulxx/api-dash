<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionAdditionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("Ajout de permissions supplémentaires...");

        // Vérifier si la table s360v02_ugrights existe et contient des données
        if (DB::getSchemaBuilder()->hasTable('s360v02_ugrights')) {
            $legacyRights = DB::table('s360v02_ugrights')->get();
            
            if ($legacyRights->isNotEmpty()) {
                $this->command->info("Trouvé {$legacyRights->count()} droit(s) dans s360v02_ugrights");
                $this->importFromLegacyTable($legacyRights);
                return;
            }
        }

        // Sinon, créer des permissions supplémentaires basées sur les besoins du système
        $this->command->info("Aucune donnée trouvée dans s360v02_ugrights. Création de permissions supplémentaires...");
        $this->createAdditionalPermissions();
    }

    /**
     * Importer les permissions depuis la table s360v02_ugrights
     */
    private function importFromLegacyTable($legacyRights): void
    {
        $count = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($legacyRights as $legacyRight) {
            try {
                $rightName = $legacyRight->RightName ?? $legacyRight->rightname ?? $legacyRight->RIGHTNAME ?? null;
                
                if (!$rightName) {
                    continue;
                }
                
                // Convertir le nom du droit en format hiérarchique si possible
                $permissionName = $this->convertRightNameToPermission($rightName);
                
                // Vérifier si la permission existe déjà
                $existingPermission = Permission::where('name', $permissionName)->first();
                
                if ($existingPermission) {
                    $skipped++;
                    continue;
                }

                // Créer la permission
                Permission::create([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);

                $count++;
                
                if ($count % 10 === 0) {
                    $this->command->info("Importé {$count} permission(s)...");
                }
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 5) {
                    $this->command->warn("Erreur lors de l'importation du droit {$rightName}: " . $e->getMessage());
                }
            }
        }

        $this->command->info("✅ {$count} permission(s) importée(s) avec succès");
        if ($skipped > 0) {
            $this->command->info("⚠️  {$skipped} permission(s) déjà existante(s) (ignorée(s))");
        }
        if ($errors > 0) {
            $this->command->warn("⚠️  {$errors} erreur(s) rencontrée(s)");
        }
    }

    /**
     * Convertir un nom de droit legacy en format hiérarchique
     */
    private function convertRightNameToPermission(string $rightName): string
    {
        // Mapping des droits legacy vers le format hiérarchique Module/Page/Action
        $mappings = [
            'LISTE_MANIFESTE_TC' => 'Manifestes/Manifeste TC/Rechercher',
            'LISTE_MANIFESTE_TT' => 'Manifestes/Manifeste TT/Rechercher',
            'LISTE_MANIFESTE_SG' => 'Manifestes/Manifeste SG/Rechercher',
            'LISTE_SYDAMAUTO' => 'Sydam Auto/Page Sydam Auto/Rechercher',
            'LISTE_FCVR_SG' => 'FCVR/FCVR SG/Rechercher',
            'LISTE_FDI_SG' => 'FDI/FDI SG/Rechercher',
            'LISTE_FDI_ARTICLE' => 'FDI/FDI Articles/Rechercher',
            'LISTE_FCVR_ARTICLE' => 'FCVR/FCVR Articles/Rechercher',
            'BON_PROVISOIRE_ARTICLE' => 'Bons Provisoires/Bon Provisoire Articles/Rechercher',
            'BON_PROVISOIRE_SG' => 'Bons Provisoires/Bon Provisoire SG/Rechercher',
            'Dashboard_360' => 'Accueil/Page Accueil/Voir',
            'RECHERCHE_FCVR_SG' => 'FCVR/FCVR SG/Rechercher',
            'DECLARATION_SG' => 'Déclarations/Déclaration SG/Rechercher',
            'FDI_ARTICLE' => 'FDI/FDI Articles/Rechercher',
            'LISTE_DECLARATION_ARTICLE' => 'Déclarations/Déclaration Articles/Rechercher',
            'Rch_Etat_Differentiel' => 'Controle S360°/Page pour les Controles/Rechercher',
            'Rch_Bon Provisoire_Aricles' => 'Bons Provisoires/Bon Provisoire Articles/Rechercher',
            'Rch_Bon Provisoire_SG' => 'Bons Provisoires/Bon Provisoire SG/Rechercher',
            'Rch_Declarations_SG' => 'Déclarations/Déclaration SG/Rechercher',
            'Rch_Declaration_Articles' => 'Déclarations/Déclaration Articles/Rechercher',
            'Rch_Manifeste_SG' => 'Manifestes/Manifeste SG/Rechercher',
            'Rch_Manifeste_TT' => 'Manifestes/Manifeste TT/Rechercher',
            'Rch_Manifeste_TC' => 'Manifestes/Manifeste TC/Rechercher',
            'Rch_Sydam_Auto' => 'Sydam Auto/Page Sydam Auto/Rechercher',
            'Rch_FCVR_SG' => 'FCVR/FCVR SG/Rechercher',
            'Rch_FCVR_Articles' => 'FCVR/FCVR Articles/Rechercher',
            'Rch_FDI_SG' => 'FDI/FDI SG/Rechercher',
            'Rch_FDI_Articles' => 'FDI/FDI Articles/Rechercher',
            'RchFDI_Articles' => 'FDI/FDI Articles/Rechercher',
            'Rch_Ctrl_ManDec' => 'Controle S360°/Page pour les Controles/Rechercher',
            'Rch_Ctrl_RfcvDec' => 'Controle S360°/Page pour les Controles/Rechercher',
            'pchrg_manifeste_img' => 'Manifestes/Manifeste SG/Télécharger',
            'Rch_Dec_article_AT' => 'Déclarations/Déclaration Articles/Rechercher',
            'Rch_T1' => 'Controle S360°/Page pour les Controles/Rechercher',
            'Rch_Zone_DED_S360' => 'Alertes/Page Alertes/Rechercher',
            'INFORMATION_DIFFERENTIEL' => 'Controle S360°/Page pour les Controles/Voir',
            'Rch Zone Alerte S360' => 'Alertes/Page Alertes/Rechercher',
            'LISTE_BANQUE_TVF' => 'Banque/TVF/Rechercher',
            'LISTE_BANQUE_SAD' => 'Banque/SAD/Rechercher',
            'LISTE_DECLARATION_TC' => 'Déclarations/Déclaration TC/Rechercher',
            'Contrôle FDI Compare' => 'FDI/FDI SG/Comparer',
            'Section_FDI_SG_Compare_1' => 'FDI/FDI SG/Comparer',
            'Section_FDI_SG_Compare_2' => 'FDI/FDI SG/Comparer',
            'Section_FCVR_SG_Comp_1' => 'FCVR/FCVR SG/Comparer',
            'Section_FCVR_SG_Comp_2' => 'FCVR/FCVR SG/Comparer',
            'Section_Banque_Comp_1' => 'Banque/TVF/Comparer',
            'Section_Banque_Comp_2' => 'Banque/TVF/Comparer',
            'Ecarts-Diff_FDI_Compare' => 'FDI/FDI SG/Comparer',
            'Section_Details_Article_FDI_Comp_1' => 'FDI/FDI Articles/Comparer',
            'Section_Details_Article_FDI_Comp_2' => 'FDI/FDI Articles/Comparer',
            'Section_Details_Article_RFCV_Comp_1' => 'FCVR/FCVR Articles/Comparer',
            'Section_Details_Article_RFCV_Comp_2' => 'FCVR/FCVR Articles/Comparer',
            'Produit_Unitaire_FDI_Comp_1' => 'FDI/FDI SG/Comparer',
            'Produit_Unitaire_FDI_Comp_2' => 'FDI/FDI SG/Comparer',
            'SYCOD.S360v02_users' => 'Administration/Utilisateurs/Voir',
            'admin_rights' => 'Administration/Permissions/Voir',
            'admin_members' => 'Administration/Membres/Voir',
            'admin_users' => 'Administration/Utilisateurs/Voir',
        ];

        // Si un mapping existe, l'utiliser
        if (isset($mappings[$rightName])) {
            return $mappings[$rightName];
        }

        // Sinon, essayer de convertir automatiquement
        // Format: MODULE_PAGE_ACTION -> Module/Page/Action
        $parts = explode('_', $rightName);
        if (count($parts) >= 2) {
            $module = ucfirst(strtolower($parts[0]));
            $page = ucfirst(strtolower($parts[1] ?? 'Général'));
            $action = ucfirst(strtolower($parts[2] ?? 'Voir'));
            return "{$module}/{$page}/{$action}";
        }

        // Par défaut, créer une permission générique
        return "Autre/{$rightName}/Voir";
    }

    /**
     * Créer des permissions supplémentaires basées sur les besoins du système
     */
    private function createAdditionalPermissions(): void
    {
        $additionalPermissions = [
            // Permissions pour les comparaisons
            'FDI/FDI SG/Comparer',
            'FCVR/FCVR SG/Comparer',
            'Banque/TVF/Comparer',
            'Banque/SAD/Comparer',
            
            // Permissions pour les articles
            'FDI/FDI Articles/Ajouter',
            'FDI/FDI Articles/Modifier',
            'FDI/FDI Articles/Supprimer',
            'FDI/FDI Articles/Rechercher',
            'FDI/FDI Articles/Exporter',
            'FDI/FDI Articles/Comparer',
            
            'FCVR/FCVR Articles/Ajouter',
            'FCVR/FCVR Articles/Modifier',
            'FCVR/FCVR Articles/Supprimer',
            'FCVR/FCVR Articles/Rechercher',
            'FCVR/FCVR Articles/Exporter',
            'FCVR/FCVR Articles/Comparer',
            
            // Permissions pour les déclarations TC
            'Déclarations/Déclaration TC/Ajouter',
            'Déclarations/Déclaration TC/Modifier',
            'Déclarations/Déclaration TC/Supprimer',
            'Déclarations/Déclaration TC/Rechercher',
            'Déclarations/Déclaration TC/Exporter',
            'Déclarations/Déclaration TC/Valider',
            
            // Permissions pour les déclarations articles
            'Déclarations/Déclaration Articles/Ajouter',
            'Déclarations/Déclaration Articles/Modifier',
            'Déclarations/Déclaration Articles/Supprimer',
            'Déclarations/Déclaration Articles/Rechercher',
            'Déclarations/Déclaration Articles/Exporter',
            
            // Permissions pour les bons provisoires articles
            'Bons Provisoires/Bon Provisoire Articles/Ajouter',
            'Bons Provisoires/Bon Provisoire Articles/Modifier',
            'Bons Provisoires/Bon Provisoire Articles/Supprimer',
            'Bons Provisoires/Bon Provisoire Articles/Rechercher',
            'Bons Provisoires/Bon Provisoire Articles/Exporter',
            
            // Permissions pour Sydam Auto
            'Sydam Auto/Page Sydam Auto/Ajouter',
            'Sydam Auto/Page Sydam Auto/Modifier',
            'Sydam Auto/Page Sydam Auto/Supprimer',
            'Sydam Auto/Page Sydam Auto/Rechercher',
            'Sydam Auto/Page Sydam Auto/Exporter',
            
            // Permissions pour l'administration
            'Administration/Utilisateurs/Ajouter',
            'Administration/Utilisateurs/Modifier',
            'Administration/Utilisateurs/Supprimer',
            'Administration/Utilisateurs/Rechercher',
            'Administration/Rôles/Ajouter',
            'Administration/Rôles/Modifier',
            'Administration/Rôles/Supprimer',
            'Administration/Rôles/Rechercher',
            'Administration/Permissions/Ajouter',
            'Administration/Permissions/Modifier',
            'Administration/Permissions/Supprimer',
            'Administration/Permissions/Rechercher',
        ];

        $count = 0;
        foreach ($additionalPermissions as $permissionName) {
            try {
                // Vérifier si la permission existe déjà
                $existingPermission = Permission::where('name', $permissionName)->first();
                
                if ($existingPermission) {
                    continue;
                }

                // Créer la permission
                Permission::create([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);

                $count++;
            } catch (\Exception $e) {
                $this->command->warn("Erreur lors de la création de la permission {$permissionName}: " . $e->getMessage());
            }
        }

        $this->command->info("✅ {$count} permission(s) supplémentaire(s) créée(s)");
    }
}
