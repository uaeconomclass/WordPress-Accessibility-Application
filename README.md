# Accessibility Auditor

WordPress plugin for page-level accessibility scanning and AI-assisted remediation inside Bricks Builder.

This repo is the plugin code. The local WordPress lab lives in the sibling repo `C:\GIT\wp-whittemore-lab`, where the plugin is mounted into `wp-content/plugins/accessibility-auditor`.

## What It Does

- Runs `axe-core` scans inside Bricks editor preview
- Saves page-level score, status, summary, and findings per page
- Adds admin UX for Overview, Reports, Settings, Seed Fixtures, and LLM Calls
- Supports guided Claude fixes and a growing set of page-level auto-fix flows
- Provides a real Bricks fixture system for regression work

## Current State

- Page-level scan filtering is in place: header/footer/template noise is excluded from page scoring
- Page-level filtering now correctly keeps nested preview targets that belong to current-page Bricks elements
- LLM audit logging is in place, including raw request/response payloads with API key redacted
- `Seed Fixtures` exists in wp-admin for local/dev environments
- Bricks smoke coverage now has 2 confirmed visible proof cases:
  - `link-name-inline` (`#1319`)
  - `image-alt-basic` (`#1317`)
- Fixture detectability currently sits at:
  - `35/37` page-level `scan-ready`
  - `11/37` page-level `auto-fix-ready`

Helpful status docs:

- [Current Status vs Spec](docs/requirements/whittemore-current-status-vs-spec.md)
- [Review Report](docs/review-report.md)
- [Project State](docs/memory/2026-03-20_project-state-and-dev-tooling.md)
- [Bricks Component Matrix](tests/coverage/bricks-component-matrix.md)
- [Seed Detectability Report](tests/coverage/seed-detectability-report.md)

## Repo Structure

- [accessibility-auditor.php](accessibility-auditor.php): plugin bootstrap
- [classes](classes): PHP application logic
- [assets](assets): editor/admin JS and CSS
- [scripts](scripts): WP-CLI seed helpers
- [tests](tests): unit, integration, coverage docs, and Playwright E2E
- [docs](docs): requirements, review, architecture, and project memory

## Local Development

Use the sibling WordPress lab repo:

- `C:\GIT\wp-whittemore-lab`

That repo runs the actual WordPress + Bricks environment at:

- `http://localhost:8090`

Detailed setup notes:

- [Local Development](docs/onboarding/local-development.md)

Typical workflow:

1. Start the WordPress lab from `wp-whittemore-lab`
2. Edit plugin code in this repo
3. Let `mutagen` sync the plugin into the container
4. Open Bricks / wp-admin in the lab
5. Run targeted tests here before pushing

## Admin Screens

In wp-admin the plugin now groups its tools under:

- `Accessibility Auditor -> Overview`
- `Accessibility Auditor -> Reports`
- `Accessibility Auditor -> Settings`
- `Accessibility Auditor -> Seed Fixtures`
- `Accessibility Auditor -> LLM Calls`

Notes:

- `Seed Fixtures` is dev-gated
- `LLM Calls` stores usage, latency, estimated cost, plus raw request/response payloads for new calls

## Seeding Bricks Fixtures

The main fixture script is:

- [seed_bricks_test_pages.php](scripts/seed_bricks_test_pages.php)

Run it from `C:\GIT\wp-whittemore-lab`:

```powershell
docker compose run --rm wpcli eval-file /var/www/html/wp-content/plugins/accessibility-auditor/scripts/seed_bricks_test_pages.php --path=/var/www/html --allow-root
```

Useful filters:

```powershell
$env:AA_SEED_LIST='1'
$env:AA_SEED_SCENARIO='link-name-inline'
$env:AA_SEED_RULE='link-name'
$env:AA_SEED_COMPONENT='button'
$env:AA_SEED_STRATEGY='auto-fix'
docker compose run --rm wpcli eval-file /var/www/html/wp-content/plugins/accessibility-auditor/scripts/seed_bricks_test_pages.php --path=/var/www/html --allow-root
```

## Testing

### Unit Tests

```powershell
php tests/unit/run.php
```

### Integration Tests

Run from `C:\GIT\wp-whittemore-lab`:

```powershell
docker compose run --rm wpcli eval-file /var/www/html/wp-content/plugins/accessibility-auditor/tests/integration/run.php --path=/var/www/html --allow-root
```

### Playwright E2E

See:

- [tests/e2e/README.md](tests/e2e/README.md)

Quick start:

```powershell
cd tests/e2e
npm install
npm run install:browsers
npm run test:headed
```

Targeted visible smoke examples:

```powershell
cd tests/e2e
npx playwright test tests/autofix-flow.smoke.spec.js --project=chromium --headed --no-deps --grep "Link Name fixture"
npx playwright test tests/autofix-flow.smoke.spec.js --project=chromium --headed --no-deps --grep "Image Alt fixture"
```

## Debugging and Observability

- Debug log file: `wp-content/aa-debug.log`
- LLM audit table: `wp_sc_aa_llm_calls`
- `LLM Calls` admin screen shows:
  - mode
  - status
  - tokens
  - latency
  - estimated cost
  - raw request
  - raw response

## Important Constraints

- This plugin is intentionally `page-level only`
- Site-scope issues like document title or HTML lang are not valid page-level fixtures
- Real Bricks component render behavior wins over assumptions; fixture work should validate actual rendered markup

## Next Useful Docs

- [Local Development](docs/onboarding/local-development.md)
- [Seed Detectability Report](tests/coverage/seed-detectability-report.md)
- [WCAG Rule Matrix](tests/coverage/wcag-rule-matrix.md)
