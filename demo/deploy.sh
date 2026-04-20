#!/usr/bin/env bash
# =============================================================================
# deploy.sh — Déploiement vers offlinesync.techparse.fr (cPanel SSH)
# Usage : bash deploy.sh
# =============================================================================
set -e

# ── Variables à adapter ───────────────────────────────────────────────────────
SSH_USER="techpars"                           # ton user cPanel SSH
SSH_HOST="techparse.fr"                       # ou l'IP du serveur
SSH_PORT=22
REMOTE_DIR="~/offlinesync"                    # dossier applicatif hors public_html
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)"   # répertoire courant (demo/)

echo ""
echo "═══════════════════════════════════════════════"
echo "  offlineSync — Déploiement production"
echo "  Cible : $SSH_USER@$SSH_HOST:$REMOTE_DIR"
echo "═══════════════════════════════════════════════"
echo ""

# ── 1. Vérifier que .env.production est rempli ───────────────────────────────
if grep -q "DB_DATABASE=$" "$LOCAL_DIR/.env.production" 2>/dev/null || \
   grep -qE "DB_DATABASE=\s*$" "$LOCAL_DIR/.env.production"; then
    echo "❌ Remplis d'abord .env.production (DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_KEY)"
    exit 1
fi

echo "✓ .env.production détecté"

# ── 2. Upload via rsync (SSH) ────────────────────────────────────────────────
echo ""
echo "📤 Upload des fichiers (rsync)..."

rsync -avz --progress \
    --exclude='.env' \
    --exclude='.env.local' \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='database/database.sqlite' \
    --exclude='deploy.sh' \
    -e "ssh -p $SSH_PORT" \
    "$LOCAL_DIR/" \
    "$SSH_USER@$SSH_HOST:$REMOTE_DIR/"

echo "✓ Upload terminé"

# ── 3. Commandes distantes ───────────────────────────────────────────────────
echo ""
echo "⚙️  Configuration distante..."

ssh -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" bash <<REMOTE
set -e
cd $REMOTE_DIR

echo "→ Copie du .env de production..."
cp .env.production .env

echo "→ Permissions storage & bootstrap..."
chmod -R 775 storage bootstrap/cache
chown -R \$(whoami): storage bootstrap/cache

echo "→ Optimisation autoload..."
composer dump-autoload --optimize --no-dev 2>/dev/null || true

echo "→ Cache de configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "→ Migrations..."
php artisan migrate --force

echo "→ Seed utilisateur de démo..."
php artisan db:seed --force 2>/dev/null || true

echo ""
echo "✅ Déploiement terminé !"
echo "   URL : https://offlinesync.techparse.fr"
REMOTE

echo ""
echo "═══════════════════════════════════════════════"
echo "  ✅ Déploiement réussi !"
echo "  https://offlinesync.techparse.fr"
echo "  https://offlinesync.techparse.fr/mobile"
echo "═══════════════════════════════════════════════"
echo ""
