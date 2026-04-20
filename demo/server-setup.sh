#!/usr/bin/env bash
# =============================================================================
# server-setup.sh — Premier déploiement sur le serveur cPanel
# À lancer UNE SEULE FOIS via SSH après avoir cloné le repo
#
# Prérequis côté cPanel (avant de lancer ce script) :
#   1. Créer le sous-domaine offlinesync.techparse.fr
#      → Document Root : offlinesync/demo/public
#   2. Créer la base MySQL + utilisateur dans cPanel > MySQL Databases
#   3. Cloner le repo :
#      git clone https://github.com/TON_USER/TON_REPO.git ~/offlinesync
#   4. Copier et remplir le .env :
#      cp ~/offlinesync/demo/.env.production ~/offlinesync/demo/.env
#      nano ~/offlinesync/demo/.env   (remplir DB_*, APP_KEY)
#   Ensuite lancer ce script :
#      bash ~/offlinesync/demo/server-setup.sh
# =============================================================================
set -e

DEMO_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DEMO_DIR"

echo ""
echo "═══════════════════════════════════════════"
echo "  offlineSync — Setup initial serveur"
echo "  Répertoire : $DEMO_DIR"
echo "═══════════════════════════════════════════"

# Vérifier que le .env existe et est configuré
if [ ! -f ".env" ]; then
    echo "❌ Fichier .env introuvable."
    echo "   cp .env.production .env && nano .env"
    exit 1
fi

if grep -qE "^DB_DATABASE=\s*$" .env; then
    echo "❌ DB_DATABASE n'est pas rempli dans .env"
    exit 1
fi

echo "✓ .env détecté"

# Dépendances
echo ""
echo "→ Installation des dépendances..."
composer install --no-dev --optimize-autoloader --no-interaction

# Clé d'application
echo ""
echo "→ Génération de la clé d'application..."
php artisan key:generate --force

# Permissions
echo ""
echo "→ Permissions storage & bootstrap/cache..."
chmod -R 775 storage bootstrap/cache

# Migrations
echo ""
echo "→ Migrations..."
php artisan migrate --force

# Seed (utilisateur de démo)
echo ""
echo "→ Seed utilisateur de démo..."
php artisan db:seed --force

# Caches Laravel
echo ""
echo "→ Mise en cache de la config, routes, vues..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "═══════════════════════════════════════════"
echo "  ✅ Setup terminé !"
echo ""
echo "  Configurer GitHub Secrets dans le repo :"
echo "  SSH_HOST     → techparse.fr"
echo "  SSH_USER     → $(whoami)"
echo "  SSH_PORT     → 22"
echo "  REMOTE_DIR   → $DEMO_DIR/.."
echo "  SSH_PRIVATE_KEY → contenu de ~/.ssh/id_ed25519"
echo "═══════════════════════════════════════════"
echo ""
