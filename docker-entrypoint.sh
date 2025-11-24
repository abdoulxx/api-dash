#!/bin/bash

set -e

echo "🚀 Starting S360 API initialization..."

# Wait for PostgreSQL
echo "⏳ Waiting for PostgreSQL..."
until nc -z postgres 5432 2>/dev/null; do
  echo "   PostgreSQL is unavailable - sleeping..."
  sleep 2
done
echo "✅ PostgreSQL is ready!"

# Wait for Redis
echo "⏳ Waiting for Redis..."
until nc -z redis 6379 2>/dev/null; do
  echo "   Redis is unavailable - sleeping..."
  sleep 2
done
echo "✅ Redis is ready!"

# Set proper permissions
echo "📁 Setting permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 775 /var/www/html/storage 2>/dev/null || true
chmod -R 775 /var/www/html/bootstrap/cache 2>/dev/null || true

# Install Composer dependencies
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
else
    echo "✅ Composer dependencies already installed"
fi

# Install Node dependencies
if [ ! -d "node_modules" ] || [ ! -f "node_modules/.package-lock.json" ]; then
    echo "📦 Installing Node dependencies..."
    npm install
else
    echo "✅ Node dependencies already installed"
fi

# Create .env if it doesn't exist
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file..."
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "✅ .env file created from .env.example"
    else
        echo "⚠️  Warning: .env.example not found!"
    fi
fi

# Update .env with Docker environment variables if needed
if [ -f ".env" ]; then
    # Update DB_HOST if not already set to postgres
    if ! grep -q "DB_HOST=postgres" .env; then
        sed -i 's/DB_HOST=.*/DB_HOST=postgres/' .env 2>/dev/null || \
        sed -i '' 's/DB_HOST=.*/DB_HOST=postgres/' .env 2>/dev/null || true
    fi
    
    # Update REDIS_HOST if not already set to redis
    if ! grep -q "REDIS_HOST=redis" .env; then
        sed -i 's/REDIS_HOST=.*/REDIS_HOST=redis/' .env 2>/dev/null || \
        sed -i '' 's/REDIS_HOST=.*/REDIS_HOST=redis/' .env 2>/dev/null || true
    fi
    
    # Set CACHE_STORE to redis if not set
    if ! grep -q "CACHE_STORE=" .env; then
        echo "CACHE_STORE=redis" >> .env
    fi
    
    # Set QUEUE_CONNECTION to redis if not set
    if ! grep -q "QUEUE_CONNECTION=" .env; then
        echo "QUEUE_CONNECTION=redis" >> .env
    fi
fi

# Generate application key if not set
if [ -f ".env" ] && ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo "🔑 Generating application key..."
    php artisan key:generate --ansi --force || true
fi

# Build assets if not built
if [ ! -d "public/build" ] || [ ! -f "public/build/manifest.json" ]; then
    echo "🎨 Building assets..."
    npm run build || echo "⚠️  Asset build failed, continuing..."
else
    echo "✅ Assets already built"
fi

# Run migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force || echo "⚠️  Migrations may have already been run"

# Clear caches
echo "🧹 Clearing caches..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Create storage link
if [ ! -L "public/storage" ]; then
    echo "🔗 Creating storage link..."
    php artisan storage:link || true
fi

# Optimize for production
echo "⚡ Optimizing application..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "✅ Application is ready!"
echo "🌐 API available at: http://localhost"
echo "📚 Swagger docs at: http://localhost/api/documentation"

exec "$@"

