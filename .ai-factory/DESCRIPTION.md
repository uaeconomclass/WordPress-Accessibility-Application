# Project: Accessibility Auditor for WordPress / Bricks

## Overview

WordPress-Accessibility-Application is a WordPress plugin that runs page-level accessibility scans inside the Bricks Builder preview, stores normalized results in WordPress, and supports guided and automatic remediation workflows for selected accessibility issues.

The project is currently a development-stage plugin with a pragmatic architecture: PHP powers the plugin runtime and REST/AJAX surface, JavaScript powers the Bricks/editor experience, and Playwright is used for end-to-end smoke coverage.

## Detected Stack

- Runtime: WordPress plugin, PHP
- Editor/UI: JavaScript assets loaded in Bricks preview/admin contexts
- Persistence: WordPress options, post meta, custom scan table
- Accessibility engine: `axe-core`
- AI integration: Anthropic Claude API
- E2E testing: Playwright in `tests/e2e`
- Local dev environment: `.wp-env.json`

## Main Entry Points

- Plugin bootstrap: `accessibility-auditor.php`
- Module loader: `classes/Loader.php`
- Scan pipeline: `classes/ScanManager.php`
- AI flow/controller: `classes/AI.php`
- Extracted AI support classes:
  - `classes/ClaudeClient.php`
  - `classes/AiResponseNormalizer.php`
  - `classes/BricksPatchValidator.php`
  - `classes/BricksPatchApplier.php`
  - `classes/BricksElementFinder.php`
- Admin/reporting:
  - `classes/Pages_Column.php`
  - `classes/Report.php`
  - `classes/Revisions.php`
  - `classes/Settings.php`
  - `classes/Admin/Admin.php`

## Current Architecture Notes

- The project has already moved beyond the earliest "single monolith" state by extracting several AI-related helpers from `AI.php`.
- `AI.php` still appears to be the orchestration-heavy hotspot and remains the primary candidate for further refactoring.
- The scan workflow is centered on rendered Bricks preview DOM analysis and Bricks JSON patch application, which is the correct conceptual model for this plugin.
- Documentation is richer in `docs/architecture`, `docs/requirements`, and `docs/memory` than in the root `README.md`.

## Current Gaps / Risks

- Claude calls are still documented as synchronous and likely remain a UX and reliability risk until async/background execution is introduced.
- The project has no obvious CI configuration in the repository root.
- The root `README.md` is too minimal to onboard a new developer by itself.
- There is active local work in progress under `tests/e2e`, so doc updates should avoid assuming a clean worktree.

## Recommended Skills For This Repo

### Use by default

- `ai-factory.fix` for focused bug-fixing work inside the plugin
- `ai-factory.review` for review passes on PHP/JS changes
- `playwright` for smoke flow validation in Bricks / wp-admin
- `security-review` when touching REST routes, permissions, or API key handling

### Use when planning bigger work

- `ai-factory.task` to break Phase 2 work into tracked steps
- `ai-factory.improve` to refine the plan after the first pass
- `ai-factory.implement` to execute a saved plan incrementally
- `ai-factory.architecture` when continuing the AI pipeline refactor

### Optional, depending on the next task

- `php-pro` for stricter PHP modernization and standards work
- `ai-factory.security-checklist` for a broader pre-release security pass

## Recommended MCP / Tooling Focus

- `github` for issue and PR tracking
- `playwright` or browser MCP for end-to-end verification
- `serena` for codebase exploration, targeted edits, and project memory

## Suggested Next Steps

1. Expand onboarding docs beyond the root README.
2. Confirm current unit/integration/e2e execution status on this machine.
3. Continue reducing responsibilities in `classes/AI.php`.
4. Decide which subset of auto-fix rules is officially supported in the next milestone.
