# Orderly

A monorepo for an order management system built for teams handling sales through manual channels such as social media, direct messages, and phone orders.

## Features

- Orders, shipments, and returns workflows
- Inventory tracking and movement history
- Product and customer management
- Role-based access and team administration
- Reporting and operational dashboards

## Project Structure

- `services/api` Laravel 12 backend API
- `services/web` Vue 3 + TypeScript frontend

## Architecture

Orderly is organized as a backend API and a separate frontend application in the same repository.

- **Backend:** Laravel 12 provides the API, domain services, validation, authentication, and persistence
- **Frontend:** Vue 3 consumes the API through a single-page application for operational workflows
- **Database:** relational storage for orders, shipments, returns, inventory, products, customers, and users
- **Business logic:** core workflow rules live in dedicated service classes rather than controllers

## Requirements

- PHP 8.5+
- Composer
- Node.js 22+
- npm
- `gum`
- A database supported by the API configuration

## Quick Start

The recommended way to work with the project is through the interactive development menu.

### 1. Configure the API

```bash
cd services/api
cp .env.example .env
php artisan key:generate
```

Update `.env` with your local database settings.

### 2. Launch the development menu

```bash
make gum
```

From the menu you can:

- install all dependencies for the API and web app
- start both services together
- start the API only
- start the web app only
- run API tests
- run web tests

`make dev` is available as an alias for the same menu.

### 3. Prepare the database

After installing dependencies, run:

```bash
cd services/api
php artisan migrate
php artisan db:seed
```

Seeding is recommended for local development.

## Manual Commands

If you need to work without the menu:

### API

```bash
cd services/api
composer install
php artisan serve
php artisan test --compact
php artisan format
```

### Web

```bash
cd services/web
npm install
npm run dev
npm run test
npm run type-check
npm run build
npm run lint
```

## Development Notes

- The interactive menu is implemented in `scripts/dev.sh`
- Git hooks are stored in `.githooks/`
- The menu can install dependencies and start both services from one place

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.
