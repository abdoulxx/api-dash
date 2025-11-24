<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("Peuplement de la table admins (utilisateurs avec is_admin=true)...");

        // Vérifier si la table s360v02_users existe et contient des données
        if (DB::getSchemaBuilder()->hasTable('s360v02_users')) {
            $legacyUsers = DB::table('s360v02_users')->get();
            
            if ($legacyUsers->isNotEmpty()) {
                $this->command->info("Trouvé {$legacyUsers->count()} utilisateur(s) dans s360v02_users");
                $this->importFromLegacyTable($legacyUsers);
                return;
            }
        }

        // Sinon, créer des admins de test
        $this->command->info("Aucune donnée trouvée dans s360v02_users. Création d'admins de test...");
        $this->createTestAdmins();
    }

    /**
     * Importer les admins depuis la table s360v02_users
     */
    private function importFromLegacyTable($legacyUsers): void
    {
        $count = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($legacyUsers as $legacyUser) {
            try {
                // Vérifier si l'admin existe déjà par email ou username
                $existingAdmin = null;
                if ($legacyUser->email ?? null) {
                    $existingAdmin = User::where('email', $legacyUser->email)
                        ->where('is_admin', true)
                        ->first();
                } elseif ($legacyUser->username ?? null) {
                    $potentialEmail = strtolower($legacyUser->username) . '@example.com';
                    $existingAdmin = User::where('email', $potentialEmail)
                        ->where('is_admin', true)
                        ->first();
                }

                if ($existingAdmin) {
                    $skipped++;
                    continue;
                }

                // Préparer les données pour l'admin
                $adminData = $this->mapLegacyUserToAdmin($legacyUser);

                // Créer l'admin avec Eloquent pour générer automatiquement l'ULID
                $admin = User::create($adminData);

                $count++;
                
                if ($count % 10 === 0) {
                    $this->command->info("Importé {$count} admin(s)...");
                }
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 5) {
                    $this->command->warn("Erreur lors de l'importation de l'admin ID {$legacyUser->id}: " . $e->getMessage());
                }
            }
        }

        $this->command->info("✅ {$count} admin(s) importé(s) avec succès");
        if ($skipped > 0) {
            $this->command->info("⚠️  {$skipped} admin(s) déjà existant(s) (ignoré(s))");
        }
        if ($errors > 0) {
            $this->command->warn("⚠️  {$errors} erreur(s) rencontrée(s)");
        }
    }

    /**
     * Mapper les données de s360v02_users vers un admin
     */
    private function mapLegacyUserToAdmin($legacyUser): array
    {
        // Extraire firstname et lastname depuis fullname ou username
        $firstname = null;
        $lastname = null;
        $name = $legacyUser->fullname ?? $legacyUser->username ?? 'Administrateur';

        if ($legacyUser->fullname ?? null) {
            $nameParts = explode(' ', trim($legacyUser->fullname), 2);
            $firstname = $nameParts[0] ?? null;
            $lastname = $nameParts[1] ?? null;
        } elseif ($legacyUser->username ?? null) {
            $firstname = $legacyUser->username;
        }

        // Générer un email si manquant
        $email = $legacyUser->email ?? null;
        if (!$email && ($legacyUser->username ?? null)) {
            $email = strtolower($legacyUser->username) . '@admin.example.com';
        } elseif (!$email) {
            $email = 'admin' . ($legacyUser->id ?? time()) . '@admin.example.com';
        }

        // Hash du mot de passe
        $password = ($legacyUser->password ?? null)
            ? Hash::make($legacyUser->password)
            : Hash::make('admin123'); // Mot de passe par défaut pour les admins

        // Déterminer le statut
        $isActive = isset($legacyUser->active) && $legacyUser->active == 1;
        $statut = $isActive ? 'Actif' : 'Inactif';

        return [
            'name' => $name,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'password' => $password,
            'is_admin' => true, // Force admin
            'is_active' => $isActive,
            'statut' => $statut,
            'fonction' => 'Administrateur',
            'departement' => 'Administration',
            'manager_id' => null,
            'phone' => null,
            'address' => null,
        ];
    }

    /**
     * Créer des admins de test
     */
    private function createTestAdmins(): void
    {
        $testAdmins = [
            [
                'name' => 'Super Admin',
                'firstname' => 'Super',
                'lastname' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123'),
                'is_admin' => true,
                'is_active' => true,
                'statut' => 'Actif',
                'fonction' => 'Administrateur Système',
                'departement' => 'Administration',
            ],
            [
                'name' => 'Admin Technique',
                'firstname' => 'Admin',
                'lastname' => 'Technique',
                'email' => 'admin.tech@example.com',
                'password' => Hash::make('admin123'),
                'is_admin' => true,
                'is_active' => true,
                'statut' => 'Actif',
                'fonction' => 'Administrateur Technique',
                'departement' => 'IT',
            ],
            [
                'name' => 'Admin Douanes',
                'firstname' => 'Admin',
                'lastname' => 'Douanes',
                'email' => 'admin.douanes@example.com',
                'password' => Hash::make('admin123'),
                'is_admin' => true,
                'is_active' => true,
                'statut' => 'Actif',
                'fonction' => 'Administrateur Douanes',
                'departement' => 'Douanes',
            ],
        ];

        $count = 0;
        foreach ($testAdmins as $adminData) {
            try {
                // Vérifier si l'admin existe déjà
                if (User::where('email', $adminData['email'])->where('is_admin', true)->exists()) {
                    continue;
                }

                User::create($adminData);
                $count++;
            } catch (\Exception $e) {
                $this->command->warn("Erreur lors de la création de l'admin {$adminData['email']}: " . $e->getMessage());
            }
        }

        $this->command->info("✅ {$count} admin(s) de test créé(s)");
    }
}
