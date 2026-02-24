# E2E Smoke (Playwright)

Bricks + WP admin smoke tests with reusable login session.

## Install

```bash
cd tests/e2e
npm install
npm run install:browsers
```

## Run

Headless:

```bash
npm test
```

Headed (visible browser):

```bash
npm run test:headed
```

Interactive UI runner:

```bash
npm run test:ui
```

## Environment

By default the config loads `C:/GIT/wp-whittemore-lab/.env` and uses:

- `WP_URL`
- `WP_ADMIN_USER`
- `WP_ADMIN_PASSWORD`

You can override with env vars before running tests.
