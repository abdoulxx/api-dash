# 🐳 Docker Setup pour S360 API

Ce projet est maintenant **complètement dockerisé** et peut être démarré avec une seule commande !

## 🚀 Démarrage ultra-simple

### Option 1 : Une seule commande (recommandé)

```bash
docker-compose up
```

C'est tout ! Le script d'initialisation automatique va :
- ✅ Attendre que PostgreSQL et Redis soient prêts
- ✅ Installer les dépendances Composer et Node.js
- ✅ Créer le fichier `.env` depuis `.env.example` si nécessaire
- ✅ Configurer automatiquement les variables d'environnement Docker
- ✅ Générer la clé d'application Laravel
- ✅ Construire les assets
- ✅ Exécuter les migrations
- ✅ Optimiser l'application

### Option 2 : En arrière-plan

```bash
docker-compose up -d
```

## 📋 Prérequis

- **Docker Desktop** (Windows/Mac) ou **Docker Engine + Docker Compose** (Linux)
- **Git** (pour cloner le projet)

## 🎯 Première utilisation

### 1. Cloner le projet
```bash
git clone <votre-repo>
cd api-dash
```

### 2. Démarrer avec Docker
```bash
docker-compose up
```

**C'est tout !** Le script d'initialisation fait le reste automatiquement.

### 3. Accéder à l'application

Une fois les conteneurs démarrés (attendez 1-2 minutes pour la première fois) :

- **API** : http://localhost
- **Swagger Documentation** : http://localhost/api/documentation

## 📦 Services Docker

Le `docker-compose.yml` inclut 5 services :

| Service | Description | Port |
|---------|-------------|------|
| **app** | Application PHP-FPM (Laravel) | 9000 |
| **nginx** | Serveur web Nginx | 80 |
| **postgres** | Base de données PostgreSQL | 5432 |
| **redis** | Cache Redis | 6379 |
| **queue** | Worker de file d'attente Laravel | - |

## 🔧 Commandes utiles

### Démarrer les conteneurs
```bash
docker-compose up          # Avec logs visibles
docker-compose up -d       # En arrière-plan
```

### Arrêter les conteneurs
```bash
docker-compose down        # Arrêter et supprimer les conteneurs
docker-compose stop       # Arrêter sans supprimer
```

### Voir les logs
```bash
# Tous les services
docker-compose logs -f

# Un service spécifique
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f postgres
docker-compose logs -f redis
docker-compose logs -f queue
```

### Exécuter des commandes Artisan
```bash
docker-compose exec app php artisan <commande>
```

Exemples :
```bash
# Lister les routes
docker-compose exec app php artisan route:list

# Vider le cache
docker-compose exec app php artisan cache:clear

# Exécuter les migrations
docker-compose exec app php artisan migrate

# Exécuter les seeders
docker-compose exec app php artisan db:seed

# Créer un utilisateur
docker-compose exec app php artisan tinker
```

### Accéder au shell du conteneur
```bash
docker-compose exec app bash
```

### Reconstruire les conteneurs
```bash
docker-compose build --no-cache
docker-compose up -d
```

### Voir les conteneurs en cours d'exécution
```bash
docker-compose ps
```

## 🗄️ Base de données

### Accéder à PostgreSQL
```bash
docker-compose exec postgres psql -U user_postgre -d s360
```

### Sauvegarder la base de données
```bash
docker-compose exec postgres pg_dump -U user_postgre s360 > backup.sql
```

### Restaurer la base de données
```bash
docker-compose exec -T postgres psql -U user_postgre s360 < backup.sql
```

## 🔴 Redis

### Accéder à Redis CLI
```bash
docker-compose exec redis redis-cli
```

### Voir les clés Redis
```bash
docker-compose exec redis redis-cli KEYS "*"
```

### Vider le cache Redis
```bash
docker-compose exec redis redis-cli FLUSHALL
```

## 🧹 Nettoyage

### Supprimer tous les conteneurs et volumes
```bash
docker-compose down -v
```

### Supprimer les images
```bash
docker-compose down --rmi all
```

### Nettoyer complètement (conteneurs + volumes + images)
```bash
docker-compose down -v --rmi all
docker system prune -a
```

## 🔍 Dépannage

### Les conteneurs ne démarrent pas
```bash
# Vérifier les logs
docker-compose logs

# Vérifier l'état des conteneurs
docker-compose ps

# Vérifier les ports utilisés
netstat -ano | findstr :80    # Windows
lsof -i :80                   # Linux/Mac
```

### Erreur de permissions
```bash
# Donner les permissions au dossier storage
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Reconstruire complètement
```bash
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
```

### Le fichier .env n'est pas créé automatiquement
Si le fichier `.env.example` n'existe pas, créez-le manuellement :
```bash
# Créer .env depuis un template
cat > .env << EOF
APP_NAME=S360
APP_ENV=local
APP_KEY=
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=s360
DB_USERNAME=user_postgre
DB_PASSWORD=5327

REDIS_HOST=redis
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
EOF
```

### Les migrations ne s'exécutent pas
```bash
# Exécuter manuellement
docker-compose exec app php artisan migrate --force
```

## 📝 Configuration automatique

Le script `docker-entrypoint.sh` configure automatiquement :

- ✅ **DB_HOST** → `postgres` (nom du service Docker)
- ✅ **REDIS_HOST** → `redis` (nom du service Docker)
- ✅ **CACHE_STORE** → `redis`
- ✅ **QUEUE_CONNECTION** → `redis`
- ✅ Génération de `APP_KEY` si manquant
- ✅ Installation des dépendances si manquantes
- ✅ Construction des assets si manquants

## 🚀 Production

Pour la production, modifiez dans `.env` :
- `APP_ENV=production`
- `APP_DEBUG=false`
- Utilisez des secrets Docker ou un gestionnaire de secrets
- Configurez un reverse proxy (Nginx/Traefik) devant les conteneurs
- Utilisez `docker-compose.prod.yml` pour une configuration séparée

## 📚 Ressources

- [Documentation Docker](https://docs.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)
- [Laravel Documentation](https://laravel.com/docs)
