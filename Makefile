.PHONY: up down build restart logs shell migrate seed test clean

# Démarrer les conteneurs
up:
	docker-compose up -d

# Arrêter les conteneurs
down:
	docker-compose down

# Reconstruire les conteneurs
build:
	docker-compose build --no-cache

# Redémarrer les conteneurs
restart: down up

# Voir les logs
logs:
	docker-compose logs -f

# Accéder au shell de l'application
shell:
	docker-compose exec app bash

# Exécuter les migrations
migrate:
	docker-compose exec app php artisan migrate

# Exécuter les seeders
seed:
	docker-compose exec app php artisan db:seed

# Exécuter les tests
test:
	docker-compose exec app php artisan test

# Installer les dépendances
install:
	docker-compose exec app composer install
	docker-compose exec app npm install
	docker-compose exec app npm run build

# Vider le cache
clear:
	docker-compose exec app php artisan cache:clear
	docker-compose exec app php artisan config:clear
	docker-compose exec app php artisan route:clear
	docker-compose exec app php artisan view:clear

# Nettoyer complètement
clean:
	docker-compose down -v
	docker system prune -f

# Setup initial
setup: build up
	sleep 10
	docker-compose exec app composer install
	docker-compose exec app npm install
	docker-compose exec app php artisan key:generate
	docker-compose exec app php artisan migrate
	docker-compose exec app php artisan storage:link
	docker-compose exec app npm run build

