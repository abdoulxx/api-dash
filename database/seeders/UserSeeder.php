<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("Importation des utilisateurs depuis s360v02_users vers users...");

        // Vérifier si la table s360v02_users existe
        if (!DB::getSchemaBuilder()->hasTable('s360v02_users')) {
            $this->command->warn("La table s360v02_users n'existe pas. Création de données de test...");
            $this->createTestUsers();
            return;
        }

        // Compter les utilisateurs existants dans s360v02_users
        $legacyUsers = DB::table('s360v02_users')->get();
        
        if ($legacyUsers->isEmpty()) {
            $this->command->warn("Aucun utilisateur trouvé dans s360v02_users. Création de données de test...");
            $this->createTestUsers();
            return;
        }

        $this->command->info("Trouvé {$legacyUsers->count()} utilisateur(s) dans s360v02_users");

        $count = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($legacyUsers as $legacyUser) {
            try {
                // Vérifier si l'utilisateur existe déjà par email ou username
                $existingUser = null;
                if ($legacyUser->email) {
                    $existingUser = User::where('email', $legacyUser->email)->first();
                } elseif ($legacyUser->username) {
                    // Chercher par email avec le username comme base
                    $potentialEmail = $legacyUser->username . '@example.com';
                    $existingUser = User::where('email', $potentialEmail)->first();
                }

                if ($existingUser) {
                    $skipped++;
                    continue;
                }

                // Préparer les données pour la table users moderne
                $userData = $this->mapLegacyUserToModern($legacyUser);

                // Créer l'utilisateur avec Eloquent pour générer automatiquement l'ULID
                $user = User::create($userData);

                $count++;
            } catch (\Exception $e) {
                $errors++;
                $this->command->warn("Erreur lors de l'importation de l'utilisateur ID {$legacyUser->id}: " . $e->getMessage());
            }
        }

        $this->command->info("✅ {$count} utilisateur(s) importé(s) avec succès");
        if ($skipped > 0) {
            $this->command->info("⚠️  {$skipped} utilisateur(s) déjà existant(s) (ignoré(s))");
        }
        if ($errors > 0) {
            $this->command->warn("⚠️  {$errors} erreur(s) rencontrée(s)");
        }
    }

    /**
     * Mapper les données de s360v02_users vers la structure users moderne
     */
    private function mapLegacyUserToModern($legacyUser): array
    {
        // Extraire firstname et lastname depuis fullname
        $firstname = null;
        $lastname = null;
        $name = $legacyUser->fullname ?? $legacyUser->username ?? 'Utilisateur';

        if ($legacyUser->fullname) {
            $nameParts = explode(' ', trim($legacyUser->fullname), 2);
            $firstname = $nameParts[0] ?? null;
            $lastname = $nameParts[1] ?? null;
        } elseif ($legacyUser->username) {
            $firstname = $legacyUser->username;
        }

        // Générer un email si manquant
        $email = $legacyUser->email;
        if (!$email && $legacyUser->username) {
            $email = strtolower($legacyUser->username) . '@example.com';
        } elseif (!$email) {
            $email = 'user' . $legacyUser->id . '@example.com';
        }

        // Déterminer le statut
        $isActive = isset($legacyUser->active) && $legacyUser->active == 1;
        $statut = $isActive ? 'Actif' : 'Inactif';

        // Hash du mot de passe
        $password = $legacyUser->password 
            ? Hash::make($legacyUser->password) 
            : Hash::make('password123'); // Mot de passe par défaut

        return [
            'name' => $name,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'password' => $password,
            'is_admin' => false, // Par défaut, pas admin
            'is_active' => $isActive,
            'statut' => $statut,
            'fonction' => null,
            'departement' => null,
            'manager_id' => null,
            'phone' => null,
            'address' => null,
        ];
    }

    /**
     * Créer des utilisateurs de test si aucune donnée n'est disponible
     */
    private function createTestUsers(): void
    {
        $testUsers = [
            [
                'name' => 'Jean Dupont',
                'firstname' => 'Jean',
                'lastname' => 'Dupont',
                'email' => 'jean.dupont@example.com',
                'password' => Hash::make('password123'),
                'is_admin' => false,
                'is_active' => true,
                'statut' => 'Actif',
                'fonction' => 'Analyste',
                'departement' => 'Recherche et Développement',
            ],
            [
                'name' => 'Marie Martin',
                'firstname' => 'Marie',
                'lastname' => 'Martin',
                'email' => 'marie.martin@example.com',
                'password' => Hash::make('password123'),
                'is_admin' => false,
                'is_active' => true,
                'statut' => 'Actif',
                'fonction' => 'Contrôleur',
                'departement' => 'Contrôle Qualité',
            ],
            [
                'name' => 'Pierre Durand',
                'firstname' => 'Pierre',
                'lastname' => 'Durand',
                'email' => 'pierre.durand@example.com',
                'password' => Hash::make('password123'),
                'is_admin' => false,
                'is_active' => true,
                'statut' => 'Actif',
                'fonction' => 'Gestionnaire',
                'departement' => 'Administration',
            ],
        ];

        $count = 0;
        foreach ($testUsers as $userData) {
            try {
                // Vérifier si l'utilisateur existe déjà
                if (User::where('email', $userData['email'])->exists()) {
                    continue;
                }

                User::create($userData);
                $count++;
            } catch (\Exception $e) {
                $this->command->warn("Erreur lors de la création de l'utilisateur {$userData['email']}: " . $e->getMessage());
            }
        }

        $this->command->info("✅ {$count} utilisateur(s) de test créé(s)");
    }
}

