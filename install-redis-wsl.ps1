# Script d'installation automatique de Redis via WSL
# Exécuter en tant qu'administrateur

Write-Host "=== Installation Redis via WSL ===" -ForegroundColor Green

# Étape 1 : Vérifier si WSL est installé
Write-Host "`n[1/6] Vérification de WSL..." -ForegroundColor Yellow
$wslInstalled = Get-Command wsl -ErrorAction SilentlyContinue

if (-not $wslInstalled) {
    Write-Host "WSL n'est pas installé. Installation..." -ForegroundColor Yellow
    
    # Activer WSL
    Write-Host "Activation de WSL..." -ForegroundColor Yellow
    dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
    
    # Activer Virtual Machine Platform
    Write-Host "Activation de Virtual Machine Platform..." -ForegroundColor Yellow
    dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
    
    Write-Host "`n⚠️  REDÉMARRAGE NÉCESSAIRE !" -ForegroundColor Red
    Write-Host "Après le redémarrage, exécutez:" -ForegroundColor Yellow
    Write-Host "  wsl --install -d Ubuntu" -ForegroundColor Cyan
    Write-Host "  wsl --set-default-version 2" -ForegroundColor Cyan
    Read-Host "Appuyez sur Entrée pour redémarrer maintenant"
    Restart-Computer
    exit
}

# Étape 2 : Vérifier les distributions WSL
Write-Host "`n[2/6] Vérification des distributions WSL..." -ForegroundColor Yellow
$distros = wsl --list --verbose

if ($distros -notmatch "Ubuntu|ubuntu") {
    Write-Host "Ubuntu n'est pas installé. Installation..." -ForegroundColor Yellow
    Write-Host "Veuillez installer Ubuntu depuis Microsoft Store:" -ForegroundColor Cyan
    Write-Host "  1. Ouvrir Microsoft Store" -ForegroundColor White
    Write-Host "  2. Rechercher 'Ubuntu' ou 'Ubuntu 22.04 LTS'" -ForegroundColor White
    Write-Host "  3. Installer Ubuntu" -ForegroundColor White
    Write-Host "  4. Ouvrir Ubuntu et créer un compte utilisateur" -ForegroundColor White
    Write-Host "  5. Relancer ce script" -ForegroundColor Yellow
    Read-Host "Appuyez sur Entrée pour continuer"
    exit
}

# Étape 3 : Vérifier le chemin du code source
Write-Host "`n[3/6] Vérification du code source Redis..." -ForegroundColor Yellow
$redisSourcePath = "C:\Users\BL235433\redis\8.2.3 source code\redis-redis-ceb01e1"

if (-not (Test-Path $redisSourcePath)) {
    Write-Host "❌ Chemin introuvable: $redisSourcePath" -ForegroundColor Red
    Write-Host "Veuillez vérifier que le code source Redis est présent." -ForegroundColor Yellow
    Read-Host "Appuyez sur Entrée pour quitter"
    exit
}

Write-Host "✓ Code source trouvé" -ForegroundColor Green

# Étape 4 : Créer le script d'installation dans WSL
Write-Host "`n[4/6] Création du script d'installation WSL..." -ForegroundColor Yellow

$wslScript = @"
#!/bin/bash
set -e

echo "=== Installation Redis depuis le code source ==="

# Chemin du code source (converti en chemin WSL)
REDIS_SOURCE="/mnt/c/Users/BL235433/redis/8.2.3 source code/redis-redis-ceb01e1"

# Vérifier que le dossier existe
if [ ! -d "`$REDIS_SOURCE" ]; then
    echo "❌ Erreur: Dossier source introuvable: `$REDIS_SOURCE"
    exit 1
fi

# Aller dans le dossier source
cd "`$REDIS_SOURCE"
echo "✓ Dossier source trouvé: `$(pwd)"

# Mettre à jour les paquets
echo "`n[1/5] Mise à jour des paquets..."
sudo apt update
sudo apt upgrade -y

# Installer les dépendances
echo "`n[2/5] Installation des dépendances..."
sudo apt install -y build-essential gcc make tcl pkg-config

# Compiler Redis
echo "`n[3/5] Compilation de Redis (cela peut prendre plusieurs minutes)..."
make distclean 2>/dev/null || true
make

# Installer Redis
echo "`n[4/5] Installation de Redis..."
sudo make install

# Créer les dossiers de configuration
echo "`n[5/5] Configuration de Redis..."
sudo mkdir -p /etc/redis /var/redis

# Copier la configuration
if [ -f redis.conf ]; then
    sudo cp redis.conf /etc/redis/redis.conf
    
    # Modifier la configuration pour accepter les connexions depuis Windows
    sudo sed -i 's/^# bind 127.0.0.1/bind 0.0.0.0/' /etc/redis/redis.conf
    sudo sed -i 's/^bind 127.0.0.1/bind 0.0.0.0/' /etc/redis/redis.conf
    sudo sed -i 's/^protected-mode yes/protected-mode no/' /etc/redis/redis.conf
    
    # Configurer le dossier de sauvegarde
    sudo sed -i 's|^dir ./|dir /var/redis|' /etc/redis/redis.conf
    sudo mkdir -p /var/redis
    sudo chmod 755 /var/redis
fi

echo "`n✅ Redis compilé et installé avec succès!"
echo ""
echo "Pour démarrer Redis:"
echo "  redis-server /etc/redis/redis.conf"
echo ""
echo "Pour tester:"
echo "  redis-cli ping"
echo ""
"@

# Sauvegarder le script temporairement
$tempScript = "$env:TEMP\install-redis-wsl.sh"
$wslScript | Out-File -FilePath $tempScript -Encoding UTF8

Write-Host "✓ Script créé" -ForegroundColor Green

# Étape 5 : Exécuter le script dans WSL
Write-Host "`n[5/6] Exécution de l'installation dans WSL..." -ForegroundColor Yellow
Write-Host "Cela peut prendre 5-10 minutes. Veuillez patienter..." -ForegroundColor Cyan

$wslPath = (wsl --list --verbose | Select-String "Ubuntu" | ForEach-Object { ($_ -split '\s+')[0] })
if (-not $wslPath) {
    $wslPath = "Ubuntu"
}

$wslTempPath = "/tmp/install-redis-wsl.sh"
wsl -d $wslPath bash -c "cp /mnt/c/Users/BL235433/AppData/Local/Temp/install-redis-wsl.sh $wslTempPath && chmod +x $wslTempPath && $wslTempPath"

# Étape 6 : Démarrer Redis
Write-Host "`n[6/6] Démarrage de Redis..." -ForegroundColor Yellow
Write-Host "`nPour démarrer Redis manuellement, exécutez dans WSL:" -ForegroundColor Cyan
Write-Host "  redis-server /etc/redis/redis.conf --daemonize yes" -ForegroundColor White

Write-Host "`n✅ Installation terminée!" -ForegroundColor Green
Write-Host "`nProchaines étapes:" -ForegroundColor Yellow
Write-Host "  1. Démarrer Redis dans WSL:" -ForegroundColor White
Write-Host "     wsl redis-server /etc/redis/redis.conf --daemonize yes" -ForegroundColor Cyan
Write-Host "  2. Tester Redis:" -ForegroundColor White
Write-Host "     wsl redis-cli ping" -ForegroundColor Cyan
Write-Host "  3. Configurer Laravel (.env):" -ForegroundColor White
Write-Host "     QUEUE_CONNECTION=redis" -ForegroundColor Cyan
Write-Host "     CACHE_STORE=redis" -ForegroundColor Cyan
Write-Host "     REDIS_HOST=127.0.0.1" -ForegroundColor Cyan
Write-Host "     REDIS_PORT=6379" -ForegroundColor Cyan

Read-Host "`nAppuyez sur Entrée pour quitter"



