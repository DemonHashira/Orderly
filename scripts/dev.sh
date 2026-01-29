#!/usr/bin/env bash
set -euo pipefail

# Paths
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="$ROOT_DIR/services/api"
WEB_DIR="$ROOT_DIR/services/web"

# Helpers
has_cmd() { command -v "$1" >/dev/null 2>&1; }

require_cmd() {
  if ! has_cmd "$1"; then
    echo "$1 is not installed."
    echo "Install with: $2"
    exit 1
  fi
}

# Requirements
require_cmd gum "brew install gum"
require_cmd php "brew install php"
require_cmd npm "brew install node"
require_cmd composer "brew install composer"

# Header
gum style \
  --border double \
  --border-foreground 212 \
  --padding "1 2" \
  --margin "1 0" \
  --bold \
  "Hello, there! Welcome to Orderly."

# Menu
choice="$(gum choose \
  --header "Orderly – Development Menu" \
  "Install ALL (API + Web)" \
  "Start ALL (API + Web)" \
  "Start API (Laravel)" \
  "Start Web (Vue)" \
  "Run API tests" \
  "Run Web tests" \
  "Exit")"

# Actions
case "$choice" in
  "Install ALL (API + Web)")
    gum style --bold "Installing dependencies..."

    gum style --foreground 212 "Configuring git hooks..."
    cd "$ROOT_DIR"
    git config core.hooksPath .githooks

    gum style --foreground 212 "Installing root dependencies..."
    npm install

    gum style --foreground 212 "Installing API dependencies (Composer)..."
    cd "$API_DIR"
    composer install

    gum style --foreground 212 "Installing Web dependencies (npm)..."
    cd "$WEB_DIR"
    npm install

    gum style --foreground 46 --bold "Installation complete!"
    ;;

  "Start ALL (API + Web)")
    gum style --bold "Starting API + Web..."

    (
      cd "$API_DIR"
      php artisan serve --ansi --host=127.0.0.1 --port=8000
    ) | sed -u 's/^/[api] /' &
    API_PID=$!

    (
      cd "$WEB_DIR"
      FORCE_COLOR=1 npm run dev
    ) | sed -u 's/^/[web] /' &
    WEB_PID=$!

    trap 'kill $API_PID $WEB_PID 2>/dev/null || true' INT TERM EXIT
    wait
    ;;

  "Start API (Laravel)")
    gum style --bold "Starting API..."
    cd "$API_DIR"
    php artisan serve --ansi --host=127.0.0.1 --port=8000
    ;;

  "Start Web (Vue)")
    gum style --bold "Starting Web..."
    cd "$WEB_DIR"
    FORCE_COLOR=1 npm run dev
    ;;

  "Run API tests")
    cd "$API_DIR"
    php artisan test
    ;;

  "Run Web tests")
    cd "$WEB_DIR"
    npm run test
    ;;

  *)
    exit 0
    ;;
esac
