<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ManifesteSg;
use App\Models\DeclarationSg;

echo "Vérification des déclarations liées aux manifestes...\n\n";

$declarations = DeclarationSg::select('num_manifeste', 'num_fdi', 'declaration', 'ulid')
    ->whereNotNull('num_manifeste')
    ->where('num_manifeste', '!=', '')
    ->limit(20)
    ->get();

echo "Déclarations avec num_manifeste:\n";
foreach ($declarations as $decl) {
    $manifeste = ManifesteSg::where('num_manifeste', $decl->num_manifeste)->first();
    echo "Déclaration: {$decl->declaration}\n";
    echo "  - Manifeste: {$decl->num_manifeste}\n";
    echo "  - FDI: {$decl->num_fdi}\n";
    echo "  - ULID: {$decl->ulid}\n";
    if ($manifeste) {
        echo "  - Manifeste trouvé: Voyage={$manifeste->num_voyage}, Bureau={$manifeste->code_bureau}\n";
    } else {
        echo "  - Manifeste NON trouvé dans manifeste_sg\n";
    }
    echo "\n";
}

// Vérifier les manifestes disponibles
echo "\nManifestes disponibles pour test:\n";
$manifestes = ManifesteSg::select('num_manifeste', 'num_voyage', 'nom_moyen_transport', 'nom_transport', 'code_bureau', 'ulid')
    ->whereNotNull('num_manifeste')
    ->where('num_manifeste', '!=', '')
    ->get();

foreach ($manifestes as $m) {
    $declCount = DeclarationSg::where('num_manifeste', $m->num_manifeste)->count();
    echo "  - {$m->num_manifeste} (Voyage: {$m->num_voyage}, Transport: {$m->nom_moyen_transport}, Déclarations: {$declCount})\n";
}

