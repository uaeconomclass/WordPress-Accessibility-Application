# AI Auto-Fix Coverage Matrix (Current vs Target)

Purpose: define what the plugin can currently auto-fix, what falls back to guided/manual fixes, and the rollout plan to maximize safe auto-fix coverage.

Spec context:

- `docs/requirements/whittemore-spec-2025-09-29.md`
- `docs/requirements/whittemore-current-status-vs-spec.md`
- `tests/coverage/wcag-rule-matrix.md`

## Scope Notes

- Detection is performed on rendered DOM in Bricks preview (`axe.run(...)`).
- Auto-fix applies patches to Bricks JSON (`bricks_data`), then re-scan verifies impact.
- "Auto-fix" here means "allowed to generate and apply a Bricks patch" (with validation).
- "Guided" means the plugin may provide Claude-generated instructions but should not auto-apply a patch.
- This matrix is rule-centric (`axe` rule IDs). The Whittemore spec also has component-centric requirements (34 Bricks components), which should be tracked in a separate component matrix.

## Scoring and Grading Constraints (Spec Reminder)

The Whittemore spec clarifies:

- `score = 100 - (issue_instances * 5)`, clamped to `0`
- letter grades:
  - `A >= 95`
  - `B >= 85`
  - `C >= 70`
  - `D >= 50`

This auto-fix matrix should be evaluated against how much it improves issue-instance counts (and therefore grades), not only rule coverage.

## Current Auto-Fix Whitelist (from `classes/AI.php`)

The plugin currently allows these `axe` rule IDs through the auto-fix path:

- `color-contrast`
- `image-alt`
- `link-name`
- `button-name`
- `frame-title`
- `input-image-alt`
- `aria-label`
- `aria-labelledby`
- `aria-hidden-focus`

Everything else currently falls back to guided instructions.

## Coverage Matrix (Current State)

Legend:

- `Detect`: detected by scan + shown in UI
- `Guided`: Claude/manual guidance can be shown
- `Auto-Fix`: patch generation + validation + apply path supported
- `Risk`: `L` low, `M` medium, `H` high implementation risk

| Rule ID | Typical WCAG Area | Detect | Guided | Auto-Fix | Current Status | Risk | Notes |
|---|---|---:|---:|---:|---|---|---|
| `image-alt` | Non-text Content (1.1.1) | Y | Y | Y | Whitelisted | L | Bricks `image` -> `settings.altText` |
| `input-image-alt` | Non-text Content (1.1.1) | Y | Y | Y | Whitelisted | L | Similar to `image-alt`; input image variants |
| `link-name` | Name/Role/Value / discernible text | Y | Y | Y | Whitelisted | M | Often patch via `aria-label`; may need content intent review |
| `button-name` | Name/Role/Value / discernible text | Y | Y | Y | Whitelisted | M | Can apply `aria-label`, but human wording may be preferable |
| `frame-title` | Name/Role/Value / frame title | Y | Y | Y | Whitelisted | L | Usually `title` / `aria-label` attribute patch |
| `aria-label` | ARIA attributes | Y | Y | Y | Whitelisted | M | Generic rule; must keep attribute allowlist strict |
| `aria-labelledby` | ARIA attributes | Y | Y | Y | Whitelisted | M | Risk if generated IDs do not exist; validate references |
| `aria-hidden-focus` | Focus mgmt / ARIA | Y | Y | Y | Whitelisted | M | Safe subset only: remove/adjust `aria-hidden`; avoid layout side effects |
| `color-contrast` | Contrast (1.4.3/1.4.11 depending case) | Y | Y | Y | Whitelisted | H | Use `_cssCustom`; must constrain selector to target element |
| `heading-order` | Info & Relationships | Y | Y | N | Guided-only target | H | Semantic intent-dependent; may require content/structure changes |
| `label` | Form labels/instructions | Y | Y | N | Guided-only target | H | Often needs real visible label + form structure knowledge |
| `aria-required-attr` | ARIA validity | Y | Y | N | Guided-only target | M | Some can be deterministic, but role context matters |
| `aria-valid-attr` | ARIA validity | Y | Y | N | Guided-only target | M | Candidate for future deterministic cleanup |
| `aria-valid-attr-value` | ARIA validity | Y | Y | N | Guided-only target | M/H | Risk depends on attribute semantics |
| `duplicate-id` | Parsing / robustness | Y | Y | N | Guided-only target | H | Global side effects; needs multi-element coordination |
| `region` / `landmark-*` | Landmarks / semantics | Y | Y | N | Guided-only target | H | Requires page-level semantics and layout intent |
| `html-has-lang` | Page language | Y | Y | N | Out of Bricks element scope | M | Usually theme/site-level, not per-element Bricks patch |
| `document-title` | Page metadata | Y | Y | N | Out of Bricks element scope | M | WP title/meta logic, not a Bricks node patch |
| `list` / `listitem` | Semantics | Y | Y | N | Guided-only target | H | Requires content restructuring |
| `tabindex` | Keyboard order | Y | Y | N | Guided-only target | H | Can break UX if auto-applied blindly |

## Phase 1 "Maximize Safe Auto-Fix" (Recommended)

Target: make auto-fix robust for a constrained subset and prove measurable value.

### Keep Auto-Fix (and harden)

- `image-alt`
- `input-image-alt`
- `frame-title`
- `link-name`
- `button-name`
- `aria-label`
- `color-contrast` (guarded)

### Conditional Auto-Fix (feature flag + validation)

- `aria-labelledby`
- `aria-hidden-focus`

Only enable auto-apply if:

- patch validator confirms allowed keys/attributes
- generated references point to existing IDs (for `aria-labelledby`)
- re-scan improves or at least does not worsen target issue count

### Guided-Only (for now)

- `heading-order`
- form labeling (`label`, `label-title-only`)
- structural/semantic rules
- global document/page metadata rules

## Expansion Plan (Rule-by-Rule Strategy Rollout)

Each promoted rule should get:

1. Strategy owner (e.g., `ImageAltFixStrategy`)
2. Prompt template with rule-specific constraints
3. Patch validator policy (allowed keys/paths)
4. Fixture(s): Bricks JSON + mock `axe` issue
5. Scenario test (find -> validate -> apply -> expected patch)
6. Re-scan verification metric

## Success Metrics (for Client Discovery / Next Phase)

Track per rule family:

- detection count
- auto-fix attempts
- validator rejects
- successful applies
- re-scan improvement rate
- manual rollback rate

This is the evidence needed to answer "Can Claude actually auto-fix accessibility issues in Bricks?" with data instead of opinions.

## Immediate Next Tasks

1. Create `tests/coverage/wcag-rule-matrix.md` as an execution copy of this matrix
2. Add scenario fixtures for top 10 rules (starting with current whitelist)
3. Implement strategy classes for the whitelist rules
4. Gate conditional rules (`aria-labelledby`, `aria-hidden-focus`) behind feature flags
5. Add metrics logging per auto-fix attempt/result
