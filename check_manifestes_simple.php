<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ManifesteSg;
use App\Models\ManifesteTt;
use App\Models\ManifesteTc;
use App\Models\DeclarationSg;

echo "Vérification des manifestes dans la base de données...\n\n";

// Compter les manifestes
$totalManifestes = ManifesteSg::count();
echo "Total manifestes: {$totalManifestes}\n\n";

// Récupérer quelques manifestes
$manifestes = ManifesteSg::select('num_manifeste', 'num_voyage', 'nom_moyen_transport', 'nom_transport', 'code_bureau', 'ulid', 'instance_id')
    ->whereNotNull('num_manifeste')
    ->where('num_manifeste', '!=', '')
    ->limit(20)
    ->get();

echo "Premiers manifestes:\n";
foreach ($manifestes as $m) {
    $ttCount = ManifesteTt::where('num_manifeste', $m->num_manifeste)->count();
    $tcCount = ManifesteTc::withoutGlobalScopes()->where('num_manifeste', $m->num_manifeste)->count();
    $declCount = DeclarationSg::where('num_manifeste', $m->num_manifeste)->count();
    
    echo "Manifeste: {$m->num_manifeste}\n";
    echo "  - Voyage: {$m->num_voyage}\n";
    echo "  - Transport: {$m->nom_moyen_transport} / {$m->nom_transport}\n";
    echo "  - Bureau: {$m->code_bureau}\n";
    echo "  - ULID: {$m->ulid}\n";
    echo "  - TT: {$ttCount}, TC: {$tcCount}, Decl: {$declCount}\n";
    echo "\n";
}

// Vérifier les TT
$totalTT = ManifesteTt::count();
echo "Total ManifesteTt: {$totalTT}\n";

// Vérifier les TC
$totalTC = ManifesteTc::withoutGlobalScopes()->count();
echo "Total ManifesteTc: {$totalTC}\n";

// Vérifier les déclarations
$totalDecl = DeclarationSg::count();
echo "Total Déclarations: {$totalDecl}\n";

