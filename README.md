# Orderly

Orderly is a monorepo for an order management system built for teams handling sales through manual channels such as social media, direct messages, and phone orders.

## What’s In This Repo

- `services/api`: Laravel 12 backend API
- `services/web`: Vue 3 + TypeScript frontend

Core product areas:

- orders, shipments, and returns workflows
- inventory tracking and movement history
- product and customer management
- role-based access and team administration
- reporting and operational dashboards

## Requirements

You need the following installed locally:

- PHP 8.5+
- Composer
- Node.js 22+
- npm
- a database supported by the Laravel API configuration
- `gum` for the interactive development menu

`gum` is not bundled with this repository and must be installed separately.

## First-Time Setup

Follow these steps in order on a fresh clone.

### 1. Install API dependencies

```bash
cd services/api
composer install
```

### 2. Create and configure the Laravel environment

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your local database settings.

After every `.env` change, run:

```bash
php artisan config:cache
```

### 3. Install web dependencies

```bash
cd ../web
npm install
```

### 4. Migrate and seed the database

```bash
cd ../api
php artisan migrate
php artisan db:seed
```

Seeding is needed for local development.

## Running The Project

You can run the project either through the interactive menu or manually.

### Option A: Interactive Development Menu

If `gum` is installed, run from the repository root:

```bash
make gum
```

`make dev` is available as an alias for the same menu.

From the menu you can:

- install dependencies for the API and web app
- start both services together
- start the API only
- start the web app only
- run API tests
- run web tests

Use this option if you want the simplest day-to-day workflow.

### Option B: Manual Commands

Use this option if you do not want the menu or prefer direct commands.

#### API

```bash
cd services/api
php artisan serve
php artisan test --compact
php artisan format
```

#### Web

```bash
cd services/web
npm run dev
npm run test
npm run type-check
npm run build
npm run lint
```

## Common Notes

- The interactive menu is implemented in `scripts/dev.sh`.
- Git hooks are stored in `.githooks/`.
- Business logic is intentionally kept in service classes rather than controllers.
- The frontend consumes the backend as a separate application, so both services need to be running for normal local development.

## Troubleshooting

### Laravel reads stale configuration

If you change `services/api/.env` and Laravel does not seem to pick it up, run:

```bash
cd services/api
php artisan config:cache
```

### `php artisan key:generate` fails

Make sure you ran `composer install` first in `services/api`.

### The development menu does not start

Make sure `gum` is installed and available in your `PATH`.

### The app boots but the API cannot connect to the database

Recheck your `services/api/.env` database credentials, then rerun:

```bash
cd services/api
php artisan config:cache
```

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.
