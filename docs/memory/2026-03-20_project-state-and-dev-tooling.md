# Project State and Dev Tooling

Last updated: 2026-03-23

## Current State Snapshot

- The repository is a WordPress plugin for accessibility scanning and AI-assisted remediation inside Bricks Builder.
- Core plugin bootstrap is stable and easy to trace: `accessibility-auditor.php` -> `classes/Loader.php`.
- The codebase already contains incremental AI refactor work:
  - `ClaudeClient.php`
  - `AiResponseNormalizer.php`
  - `BricksPatchValidator.php`
  - `BricksPatchApplier.php`
  - `BricksElementFinder.php`
- This means the repo is ahead of the older February review docs that still describe `AI.php` mostly as a single god object.
- `AI.php` is still the main orchestration hotspot, so the architectural direction in `docs/architecture/ai-autofix-refactor-plan.md` remains relevant.

## What Looks Healthy

- Clear WordPress entrypoint and class-based module layout
- Existing architecture and requirements docs under `docs/`
- Dedicated pure-PHP unit tests under `tests/unit`
- Integration tests prepared for WordPress/WP-CLI execution
- Playwright smoke coverage for key Bricks/wp-admin flows under `tests/e2e`

## Verification Performed In This Pass

- `php tests/unit/run.php` -> passed locally on 2026-03-20
- Current observed result: `103/103` unit tests passed
- Integration suite now also passes locally: `33/33`
- Seed detectability sweep now passes at `35/37` page-level scan-ready and `11/37` auto-fix-ready

## What Still Looks Risky

- Root onboarding is now much better, but still depends on a sibling WordPress lab repo and should stay aligned with real local workflow changes
- No obvious CI pipeline is present in the repo root
- AI flow still remains synchronous from the current docs/code surface
- Spec alignment docs are older than the extracted-class state now visible in `classes/`
- There is still no full automated before/after proof suite for the seeded auto-fix scenarios

## Working Tree Note

Observed during the latest pass:

- working tree was cleaned before wrap-up
- current branch now contains committed fixture, admin, and observability updates

## Recommended Skills To Keep Handy

Recommended core set for this repo:

- `ai-factory.fix`
- `ai-factory.review`
- `ai-factory.task`
- `ai-factory.implement`
- `ai-factory.architecture`
- `playwright`
- `security-review`

Useful optional add-ons:

- `php-pro` for deeper PHP cleanup and modernization
- `ai-factory.security-checklist` before release or client handoff

## How I Would Use Them Here

- `ai-factory.fix`: bugfixes in scan persistence, REST handlers, or Bricks patch flow
- `ai-factory.review`: review current changes and catch regressions
- `ai-factory.task` + `ai-factory.implement`: drive Phase 2 work in small, tracked chunks
- `ai-factory.architecture`: plan the remaining breakup of `AI.php`
- `playwright`: validate editor/admin flows after PHP or JS changes
- `security-review`: review capability checks, nonce handling, and API key storage whenever those areas change

## Documentation Follow-Up

Best next doc improvements:

1. Keep `README.md` and `docs/onboarding/local-development.md` in sync with the actual `wp-whittemore-lab` workflow.
2. Refresh spec/status docs whenever fixture coverage or auto-fix readiness numbers move.
3. Add a short before/after proof section once the automated seeded auto-fix suite exists.
