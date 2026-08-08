# IdP Kampus

**IdP Kampus** is a campus digital identity platform built on the principles of
Self-Sovereign Identity (SSI) and W3C Verifiable Credentials (VCs). Students and
staff hold their own identifiers and claim verifiable credentials issued by the
university.

The app is a server-rendered Symfony monolith with a TailAdmin 2.3.0
(Tailwind v4) admin dashboard.

## Tech Stack

- **Backend:** PHP ≥ 8.4, Symfony 8.1, Doctrine ORM 3, PostgreSQL 16
- **Templating:** Twig
- **Frontend:** TailAdmin 2.3.0 — Tailwind CSS v4, Alpine.js, ApexCharts, Webpack 5

## Pages

| Route        | Page                         | Notes                                 |
| ------------ | ---------------------------- | ------------------------------------- |
| `/`          | Dashboard                    | Real DB metric cards + demo charts    |
| `/students`  | Mahasiswa                    | Student directory with VC token state |
| `/employees` | Pegawai                      | Staff directory with VC token state   |
| `/org-units` | Unit Organisasi              | Org unit tree (code, name, parent)    |
| `/signin`    | Sign In                      | Static mock — no real auth yet        |

## Requirements

- PHP ≥ 8.4 with `pdo_pgsql`
- PostgreSQL
- Node.js + npm (to build frontend assets)

## Quick Start

```bash
# 1. Install PHP dependencies
composer install

# 2. Configure the database in .env (create .env.local to override)
#    DATABASE_URL="postgresql://USER:PASSWORD@127.0.0.1:5432/idp_system?serverVersion=16&charset=utf8"

# 3. Create the schema
php bin/console doctrine:migrations:migrate

# 4. Build frontend assets (TailAdmin webpack project -> ../public/build)
cd assets
npm install
npm run build
cd ..

# 5. Run the dev server
php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000.

## Frontend

`assets/` contains the TailAdmin webpack project (source under `assets/src/`).
Webpack emits compiled assets to `public/build/` (`bundle.js`, `style.css`,
images). Twig templates reference them via `asset('build/...')`.

> Re-run `npm run build` after changing CSS utility classes in templates —
> Tailwind v4 scans `templates/` via the `@source` directive in
> `assets/src/css/style.css`, but classes only exist in the CSS once built.

## Useful Commands

```bash
php bin/console cache:clear                 # clear var/cache
php bin/console debug:router                # list all routes
php bin/console doctrine:schema:validate    # check entity/schema sync
php bin/console doctrine:migrations:migrate # apply DB migrations
```

## Limitations

- **Sign in is a static mock** — no security bundle or real authentication yet
- **Dashboard charts are static demo data** — only the metric cards read live
  database counts
- **DID issuance/claim flow is not wired** — entities track `did`,
  `tokenHash`, `tokenStatus`, and `tokenExpiresAt`, but no issuance logic is
  implemented yet
