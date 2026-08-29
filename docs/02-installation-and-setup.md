# 02 - Installation & Setup

[← Previous: Architecture Overview](01-architecture-overview.md) | [📚 Docs Index](README.md) | [Next: Admin Panel Management →](03-admin-panel-management.md)

---

## Prerequisites

- **PHP**: 8.2 or higher
- **HashtagCMS Core**: `^2.0.6` or higher
- **Laravel Framework**: `^10.0 | ^11.0 | ^12.0 | ^13.0`

---

## 1. Installation via Composer

Install the package into your HashtagCMS project:

```bash
composer require hashtagcms/workflows
```

If developing locally using path repositories in `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../hashtagcms-workflows"
    }
],
"require": {
    "hashtagcms/workflows": "@dev"
}
```

Then run:
```bash
composer update hashtagcms/workflows
```

---

## 2. Run Database Migrations

Run Laravel's migration command to create the database tables (`workflows`, `workflow_logs`) and register the Admin Panel menu items in `cms_modules`:

```bash
php artisan migrate
```

This will execute:
1. `2026_08_21_000001_create_workflows_table.php`
2. `2026_08_21_000002_create_workflow_logs_table.php`
3. `2026_08_21_000003_add_workflow_modules_to_cms_modules.php`

---

## 3. Configuration (Optional)

Publish the package configuration file to `config/hashtagcms-workflows.php`:

```bash
php artisan vendor:publish --tag=hashtagcms-workflows-config
```

### Default Configuration Reference

```php
return [
    'page_title' => 'HashtagCMS Workflows',

    'enabled' => env('HASHTAGCMS_WORKFLOWS_ENABLED', true),

    'logging' => [
        'enabled' => true,
        'prune_days' => 30,
    ],

    'middleware' => ['web', 'auth:sanctum', 'cmsModuleInfo', 'cmsInterceptor'],
    'route_prefix' => 'admin/workflows',
    'view_prefix' => 'hashtagcms-workflows::be.workflows',
];
```

---

[← Previous: Architecture Overview](01-architecture-overview.md) | [📚 Docs Index](README.md) | [Next: Admin Panel Management →](03-admin-panel-management.md)
