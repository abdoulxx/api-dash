<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateManagersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("Mise à jour des managers pour avoir firstname et lastname...");

        // Trouver tous les managers (is_admin = true ou qui ont des managedUsers)
        // Mettre à jour même s'ils ont déjà firstname/lastname pour s'assurer qu'ils sont corrects
        $managers = User::where(function($query) {
            $query->where('is_admin', true)
                  ->orWhereHas('managedUsers');
        })
        ->whereNotNull('name')
        ->get();

        if ($managers->isEmpty()) {
            $this->command->info("Aucun manager à mettre à jour.");
            return;
        }

        $this->command->info("Trouvé {$managers->count()} manager(s) à mettre à jour");

        $count = 0;
        $errors = 0;

        foreach ($managers as $manager) {
            try {
                // Extraire firstname et lastname depuis name (forcer la mise à jour)
                $nameParts = $this->extractNameParts($manager->name);
                
                $oldFirstname = $manager->firstname;
                $oldLastname = $manager->lastname;
                
                $manager->firstname = $nameParts['firstname'];
                $manager->lastname = $nameParts['lastname'];
                $manager->save();

                $count++;
                $this->command->info("✅ Manager {$manager->email} mis à jour : firstname='{$manager->firstname}', lastname='{$manager->lastname}' (avant: '{$oldFirstname}' '{$oldLastname}')");
            } catch (\Exception $e) {
                $errors++;
                $this->command->warn("Erreur lors de la mise à jour du manager {$manager->email}: " . $e->getMessage());
            }
        }

        $this->command->info("✅ {$count} manager(s) mis à jour avec succès");
        if ($errors > 0) {
            $this->command->warn("⚠️  {$errors} erreur(s) rencontrée(s)");
        }
    }

    /**
     * Extraire firstname et lastname depuis un nom complet
     */
    private function extractNameParts(string $fullName): array
    {
        $fullName = trim($fullName);
        
        // Si le nom est vide, retourner null
        if (empty($fullName)) {
            return ['firstname' => null, 'lastname' => null];
        }

        // Diviser le nom en parties
        $parts = preg_split('/\s+/', $fullName);
        
        if (count($parts) === 1) {
            // Un seul mot : tout dans firstname
            return [
                'firstname' => $parts[0],
                'lastname' => null,
            ];
        } elseif (count($parts) === 2) {
            // Deux mots : firstname et lastname
            return [
                'firstname' => $parts[0],
                'lastname' => $parts[1],
            ];
        } else {
            // Plus de deux mots : premier mot = firstname, reste = lastname
            return [
                'firstname' => $parts[0],
                'lastname' => implode(' ', array_slice($parts, 1)),
            ];
        }
    }
}
