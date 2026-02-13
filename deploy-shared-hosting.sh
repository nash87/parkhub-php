#!/bin/bash
set -e

# ParkHub PHP Edition - Shared Hosting Deployment Script
# Creates a deployable ZIP package for shared hosting (InfinityFree, Byet, AeonFree, etc.)

echo "================================================"
echo "  ParkHub PHP - Shared Hosting Package Builder"
echo "================================================"
echo ""

# Check requirements
command -v composer >/dev/null 2>&1 || { echo "Error: composer is required"; exit 1; }
command -v npm >/dev/null 2>&1 || { echo "Error: npm is required"; exit 1; }
command -v zip >/dev/null 2>&1 || { echo "Error: zip is required"; exit 1; }

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILD_DIR="$PROJECT_DIR/build-shared"
OUTPUT="$PROJECT_DIR/parkhub-php-shared-hosting.zip"

echo "[1/5] Cleaning previous build..."
rm -rf "$BUILD_DIR" "$OUTPUT"

echo "[2/5] Installing PHP dependencies (production)..."
cd "$PROJECT_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

echo "[3/5] Building React frontend..."
npm ci --prefix "$PROJECT_DIR"
npm run build --prefix "$PROJECT_DIR"

echo "[4/5] Assembling package..."
mkdir -p "$BUILD_DIR"

# Copy everything except dev files
rsync -a --exclude='node_modules' --exclude='.git' --exclude='build-shared' \
  --exclude='tests' --exclude='phpunit.xml' --exclude='.env' \
  --exclude='*.zip' --exclude='shell.nix' --exclude='.editorconfig' \
  "$PROJECT_DIR/" "$BUILD_DIR/"

# Create .htaccess for public directory
cat > "$BUILD_DIR/public/.htaccess" << 'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS

# Root .htaccess to redirect to public/
cat > "$BUILD_DIR/.htaccess" << 'HTACCESS'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
HTACCESS

# Ensure writable directories exist
mkdir -p "$BUILD_DIR/storage/framework/sessions"
mkdir -p "$BUILD_DIR/storage/framework/views"
mkdir -p "$BUILD_DIR/storage/framework/cache"
mkdir -p "$BUILD_DIR/storage/logs"
mkdir -p "$BUILD_DIR/bootstrap/cache"
touch "$BUILD_DIR/database/database.sqlite"

# Copy .env.example
cp "$PROJECT_DIR/.env.example" "$BUILD_DIR/.env.example"

echo "[5/5] Creating ZIP package..."
cd "$BUILD_DIR"
zip -r "$OUTPUT" . -x "*.git*"

echo ""
echo "================================================"
echo "  Package created: parkhub-php-shared-hosting.zip"
echo "  Size: $(du -h "$OUTPUT" | cut -f1)"
echo "================================================"
echo ""
echo "Next steps:"
echo "  1. Upload the ZIP contents to your hosting via FTP"
echo "  2. Point your domain to the 'public/' directory"
echo "  3. Visit https://yourdomain.com/install.php"
echo "  4. Follow the setup wizard"
echo ""

# Cleanup
rm -rf "$BUILD_DIR"
