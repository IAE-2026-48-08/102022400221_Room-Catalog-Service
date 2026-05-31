#!/bin/bash
set -e

echo "🚀 Starting Room Catalog Service..."

# Copy .env if not exists
if [ ! -f .env ]; then
    cp .env.example .env
    echo "✅ .env created from .env.example"
fi

# Generate app key if placeholder
if grep -q "PLACEHOLDER_RUN_php_artisan_key_generate" .env; then
    php artisan key:generate
    echo "✅ App key generated"
fi

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL..."
until php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 2
done
echo "✅ MySQL is ready"

# Run migrations
php artisan migrate --force
echo "✅ Migrations complete"

# Seed if database is empty
ROOM_COUNT=$(php artisan tinker --execute="echo App\Models\Room::count();" 2>/dev/null | tail -1)
if [ "$ROOM_COUNT" = "0" ]; then
    php artisan db:seed --force
    echo "✅ Database seeded"
fi

# Generate Swagger docs
php artisan l5-swagger:generate || true
echo "✅ Swagger docs generated"

# Start Laravel server
echo "🌐 Server running at http://localhost:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
