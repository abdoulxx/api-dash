# Script PowerShell pour démarrer Docker sur Windows

Write-Host "=== Démarrage de S360 API avec Docker ===" -ForegroundColor Green

# Vérifier si Docker est installé
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host "❌ Docker n'est pas installé. Veuillez installer Docker Desktop." -ForegroundColor Red
    exit 1
}

# Vérifier si Docker est démarré
try {
    docker ps | Out-Null
} catch {
    Write-Host "❌ Docker n'est pas démarré. Veuillez démarrer Docker Desktop." -ForegroundColor Red
    exit 1
}

# Vérifier si .env existe
if (-not (Test-Path .env)) {
    Write-Host "⚠️  Le fichier .env n'existe pas. Création depuis .env.example..." -ForegroundColor Yellow
    if (Test-Path .env.example) {
        Copy-Item .env.example .env
        Write-Host "✅ Fichier .env créé. Veuillez le configurer avant de continuer." -ForegroundColor Green
    } else {
        Write-Host "❌ Le fichier .env.example n'existe pas." -ForegroundColor Red
        exit 1
    }
}

Write-Host "`n[1/4] Construction des images Docker..." -ForegroundColor Yellow
docker-compose build

Write-Host "`n[2/4] Démarrage des conteneurs..." -ForegroundColor Yellow
docker-compose up -d

Write-Host "`n[3/4] Attente du démarrage des services..." -ForegroundColor Yellow
Start-Sleep -Seconds 10

Write-Host "`n[4/4] Installation des dépendances et configuration..." -ForegroundColor Yellow

# Installer les dépendances Composer
Write-Host "  - Installation des dépendances Composer..." -ForegroundColor Cyan
docker-compose exec -T app composer install --no-interaction

# Installer les dépendances Node
Write-Host "  - Installation des dépendances Node..." -ForegroundColor Cyan
docker-compose exec -T app npm install

# Générer la clé d'application
Write-Host "  - Génération de la clé d'application..." -ForegroundColor Cyan
docker-compose exec -T app php artisan key:generate --force

# Exécuter les migrations
Write-Host "  - Exécution des migrations..." -ForegroundColor Cyan
docker-compose exec -T app php artisan migrate --force

# Créer le lien de stockage
Write-Host "  - Création du lien de stockage..." -ForegroundColor Cyan
docker-compose exec -T app php artisan storage:link

# Construire les assets
Write-Host "  - Construction des assets..." -ForegroundColor Cyan
docker-compose exec -T app npm run build

Write-Host "`n✅ Application démarrée avec succès !" -ForegroundColor Green
Write-Host "`n📋 Services disponibles :" -ForegroundColor Cyan
Write-Host "   - API: http://localhost" -ForegroundColor White
Write-Host "   - Swagger: http://localhost/api/documentation" -ForegroundColor White
Write-Host "`n📝 Commandes utiles :" -ForegroundColor Cyan
Write-Host "   - Voir les logs: docker-compose logs -f" -ForegroundColor White
Write-Host "   - Arrêter: docker-compose down" -ForegroundColor White
Write-Host "   - Redémarrer: docker-compose restart" -ForegroundColor White
Write-Host "   - Shell: docker-compose exec app bash" -ForegroundColor White

