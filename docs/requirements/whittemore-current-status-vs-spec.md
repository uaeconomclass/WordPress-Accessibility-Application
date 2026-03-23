# Whittemore Spec vs Current Plugin Status (Working Assessment)

Last updated: 2026-03-23  
Based on: current `feature/auto-fix` branch + live local Bricks verification, seeded fixtures, and current tests

This document maps the current plugin implementation to the Whittemore requirements snapshot.

References:

- `docs/requirements/whittemore-spec-2025-09-29.md`
- `docs/architecture/ai-autofix-coverage-matrix.md`
- `tests/coverage/wcag-rule-matrix.md`

## Executive Status

- The plugin is a strong prototype with working scan + indicators + guided fix + partial auto-fix.
- Auto-fix is now test-backed and viable for a constrained page-level rule subset.
- The biggest gaps vs spec are:
  - async/background Claude execution
  - native WP revisions UI integration (current approach uses custom snapshots)
  - user-confirmation-before-apply flow
  - usage limits / customer quotas
  - full spec-aligned component strategy docs for all families

## Functional Requirements Status

| Requirement Area | Status | Notes |
|---|---|---|
| Accessibility scanning (page-level) | Working | Scans in Bricks preview DOM using `axe-core`, then filters results down to current post page-level Bricks content only |
| WCAG-aligned findings + actionable issues | Partial / Working | Findings shown; WCAG level selector is wired; broader spec-facing mapping docs still need cleanup |
| Distinguish auto-detect vs human-review | Working | `incomplete`/manual-review concept exists in scan results/status model |
| Pages list indicators (colored dot) | Working | Custom column + page status indicator implemented |
| Indicator links to reports | Working | Report flow exists |
| Guided Fix (Claude instructions) | Working | Present and used as fallback for unsupported auto-fix rules |
| Automated Fix (Claude patch -> Bricks changes) | Partial / Working | Working for a constrained whitelist and real page-level seeded fixtures; still needs confirmation UX and broader rule/component strategy |
| Rollback after auto-fix | Working (custom snapshot) | Snapshot + reject/revert behavior works; not native WP revisions UI |
| Changelog/audit trail | Partial | Exists, but changelog detail format still needs improvement (patch summary quality) |
| Dashboard widget summary | Working | WP dashboard widget exists; top-level admin menu now groups Overview / Reports / Settings / Seed Fixtures / LLM Calls |

## Technical Requirements Status

| Technical Requirement | Status | Notes |
|---|---|---|
| WP 6.0+ compatibility | Partial | No formal matrix yet; current code targets modern WP patterns |
| Bricks integration for safe updates | Working | Uses Bricks content model (`bricks_data`) and targeted JSON patching |
| Automatic.css-aware guidance/fixes | Partial | Prompt context references ACSS; structured ACSS strategy coverage incomplete |
| Secure Claude API usage | Partial | Server-side calls/capability checks present; async/rate-limits still missing, but audit logging and admin visibility now exist |
| Revision-based rollback | Partial / Working | Custom snapshots work; native WP revisions integration gap remains |
| WP coding/security standards | Partial / Improving | Capability checks + validation present; legacy code and cleanup remain |
| Non-blocking AI calls | Missing | Current flow is synchronous |
| Usage limits per customer | Missing | Not implemented |
| Confirmation before applying AI changes | Missing / Partial | Current UX applies then allows reject; spec asks confirmation before save |
| LLM observability / cost tracking | Working (custom) | Dedicated LLM audit table + wp-admin `LLM Calls` screen now exist |

## Scoring Clarification Alignment (Requirement 1)

Current implementation behavior now aligns with clarified formula:

- page starts at `100`
- `-5` per issue instance
- clamped at `0`

Known alignment notes:

- Letter grades should be verified in UI against exact thresholds (`A/B/C/D`).
- `incomplete` / manual-review items do not reduce score in the current implementation.

## Current Auto-Fix Coverage (Rule-Level)

Current whitelist in `classes/AI.php` includes:

- `color-contrast`
- `image-alt`
- `link-name`
- `button-name`
- `frame-title`
- `input-image-alt`
- `aria-label`
- `aria-labelledby`
- `aria-hidden-focus`

Notes:

- Some whitelist rules should remain behind stricter validation / feature flags (`aria-labelledby`, `aria-hidden-focus`).
- See `docs/architecture/ai-autofix-coverage-matrix.md` for rollout recommendation.
- Live seeded fixture sweep currently shows `11/37` scenarios as `auto-fix-ready`.

## Test & Verification Status (Current)

Automated test harness now exists and is passing:

- Unit tests: `103/103` passed
- Integration tests (WP-CLI in Docker): `33/33` passed
- Seed detectability sweep: `35/37` scan-ready, `11/37` auto-fix-ready

What this validates:

- Bricks element finding (including fallback matching)
- Patch validation
- Patch application
- ScanManager scoring/meta persistence
- Snapshot round-trip / restore
- REST auth/validation behavior
- Real Bricks component fixture detectability at page-level only

## Component-Level Spec (34 Components) — Current Reality

The spec defines component-specific requirements (Heading, Form, Tabs, Carousel, SVG, etc.), but current implementation is primarily:

- `axe rule` driven
- element-JSON patch driven
- not yet a formal `component strategy` system

Recommended next step:

1. Build component/rule strategy registry
2. Map each component requirement to:
   - detect support
   - guided support
   - auto-fix support
3. Add fixtures/scenario tests for high-value components first

Current reality after the latest fixture pass:

- Page-level Bricks families are now broadly represented in seeded fixtures.
- The remaining `2/37` non-detectable scenarios are intentionally `site-scope` (`document-title`, `html-has-lang`) and outside the page-level scan boundary.

## Immediate Documentation / Engineering Next Steps

1. Keep `tests/coverage/wcag-rule-matrix.md` as the live execution checklist
2. Add a component coverage matrix (Bricks component -> WCAG -> detect/guided/auto-fix)
3. Add acceptance criteria per promoted auto-fix rule
4. Document WCAG level selector behavior and scanner tag wiring (once implemented)
5. Add metrics definitions for auto-fix success/failure/rollback rates
