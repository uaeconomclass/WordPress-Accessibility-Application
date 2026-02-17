# Project Overview

- Name: WordPress-Accessibility-Application
- Purpose: WordPress plugin for page-level accessibility scanning in Bricks Builder + Automatic.css context, with issue reporting, grading, and AI-assisted remediation workflows.
- Primary runtime: WordPress plugin (PHP), with editor/admin JavaScript UI.
- Main entrypoint: `accessibility-auditor.php` loading `classes/Loader.php`.
- Key domains:
  - Scan pipeline and persistence (`classes/ScanManager.php`)
  - Admin UX (pages column, reports, dashboard widget)
  - Settings and Claude API key storage (`classes/Settings.php`)
  - AI endpoints for guided and auto-fix flows (`classes/AI.php`)
- Notable context:
  - Repository currently has lightweight README and no explicit CI config.
  - Local WP environment is configured via `.wp-env.json` with plugin mapping to `wp-content/plugins/accessibility-auditor`.
