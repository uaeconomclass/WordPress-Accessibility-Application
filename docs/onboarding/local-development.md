# Local Development

## Repos

This project is split across two local repos:

- Plugin repo: `C:\GIT\WordPress-Accessibility-Application`
- WordPress lab: `C:\GIT\wp-whittemore-lab`

The WordPress lab mounts or syncs this plugin into:

- `/var/www/html/wp-content/plugins/accessibility-auditor`

## Local URL

- WordPress lab: `http://localhost:8090`

## Start the Lab

From `C:\GIT\wp-whittemore-lab`:

```powershell
docker compose up -d
```

If your setup uses `mutagen`, confirm the plugin sync is healthy before testing.

## Common Workflow

1. Edit plugin code in `WordPress-Accessibility-Application`
2. Let `mutagen` sync changes into the WordPress container
3. Open `http://localhost:8090/wp-admin`
4. Use `Accessibility Auditor` admin screens or open Bricks editor
5. Run targeted tests before pushing

## Key Admin Screens

- `http://localhost:8090/wp-admin/admin.php?page=aa-dashboard`
- `http://localhost:8090/wp-admin/admin.php?page=aa-scan-report`
- `http://localhost:8090/wp-admin/admin.php?page=aa-settings`
- `http://localhost:8090/wp-admin/admin.php?page=aa-seed-fixtures`
- `http://localhost:8090/wp-admin/admin.php?page=aa-llm-calls`

Notes:

- `Seed Fixtures` is intended for local/dev only
- `LLM Calls` only shows calls made after audit logging was added

## Seeding Fixtures

Run from `C:\GIT\wp-whittemore-lab`:

```powershell
docker compose run --rm wpcli eval-file /var/www/html/wp-content/plugins/accessibility-auditor/scripts/seed_bricks_test_pages.php --path=/var/www/html --allow-root
```

Useful targeting:

```powershell
$env:AA_SEED_LIST='1'
$env:AA_SEED_SCENARIO='frame-title-inline'
$env:AA_SEED_COMPONENT='carousel'
$env:AA_SEED_RULE='link-name'
$env:AA_SEED_STRATEGY='auto-fix'
docker compose run --rm wpcli eval-file /var/www/html/wp-content/plugins/accessibility-auditor/scripts/seed_bricks_test_pages.php --path=/var/www/html --allow-root
```

## Running Tests

### Unit

From the plugin repo:

```powershell
php tests/unit/run.php
```

### Integration

From `C:\GIT\wp-whittemore-lab`:

```powershell
docker compose run --rm wpcli eval-file /var/www/html/wp-content/plugins/accessibility-auditor/tests/integration/run.php --path=/var/www/html --allow-root
```

### Playwright

From the plugin repo:

```powershell
cd tests/e2e
npm install
npm run install:browsers
npm run test:headed
```

The Playwright config uses the wp-whittemore-lab `.env` by default.

## Observability

- Debug log file: `wp-content/aa-debug.log`
- LLM audit table: `wp_sc_aa_llm_calls`
- `LLM Calls` shows raw request and response payloads for new Claude calls

## Current Reality Checks

- The plugin uses a page-level-only approach
- Template/global/header/footer issues should not affect page score
- Real Bricks render behavior should be validated before adding or trusting fixtures
- Current fixture sweep status is tracked in:
  - [seed-detectability-report.md](../../tests/coverage/seed-detectability-report.md)
  - [bricks-component-matrix.md](../../tests/coverage/bricks-component-matrix.md)
