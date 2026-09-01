# KPI Dashboard

PHP/Laravel + MySQL KPI Dashboard foundation.

## Initial Admin foundation

Branch: `feature/admin-foundation`

Implemented:

- Authenticated `/admin` area (expects the project's standard Laravel `auth` middleware/scaffolding).
- User management foundation.
- User Groups and permission-based RBAC.
- Seeded groups: `Super Admin`, `KPI Admin`, `KPI Viewer`.
- Permission codes prepared for Admin, Users, KPI Dashboard, Import and Configuration.
- MySQL migrations for groups, permissions and group-permission mapping.

## Setup

```bash
php artisan migrate
php artisan db:seed --class=AdminRbacSeeder
```

> The repository was initially only a README. The branch establishes the first application code structure for the Admin/RBAC layer. Authentication UI/scaffolding is intentionally left to the selected Laravel authentication stack before deployment.
