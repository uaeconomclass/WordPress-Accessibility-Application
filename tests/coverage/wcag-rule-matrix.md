# WCAG / axe Rule Coverage Matrix (Working Copy)

Use this file as the team's live checklist for what is implemented and verified.

Status values:

- `detect-only`
- `guided`
- `auto-fix`
- `auto-fix (flagged)`
- `not-planned`

Verification values:

- `fixture`
- `unit`
- `integration`
- `manual`
- `prod-observed`

## Current Rule Coverage

| Rule ID | Status | Strategy | Verification | Notes |
|---|---|---|---|---|
| `image-alt` | auto-fix | `ImageAltFixStrategy` (planned) / current generic | fixture, integration, manual | Uses `settings.altText` |
| `input-image-alt` | auto-fix | `InputImageAltFixStrategy` (planned) / current generic | fixture (pending), manual | Same patch path family as `image-alt` |
| `link-name` | auto-fix | `LinkNameFixStrategy` (planned) / current generic | fixture, unit (finder fallback), manual | Often via `aria-label` |
| `button-name` | auto-fix | `ButtonNameFixStrategy` (planned) / current generic | fixture (pending), manual | Confirm Bricks element variants |
| `frame-title` | auto-fix | `FrameTitleFixStrategy` (planned) / current generic | fixture, manual | Can patch `title`/`aria-label` |
| `color-contrast` | auto-fix | `ColorContrastFixStrategy` (planned) / current generic | fixture, unit (validator), manual | `_cssCustom` path constrained by validator |
| `aria-label` | auto-fix | `AriaLabelFixStrategy` (planned) / current generic | fixture (pending), manual | Attribute allowlist enforced |
| `aria-labelledby` | auto-fix (flagged) | `AriaLabelledByFixStrategy` (planned) | fixture (pending), manual | Needs ID existence validation |
| `aria-hidden-focus` | auto-fix (flagged) | `AriaHiddenFocusFixStrategy` (planned) | fixture (pending), manual | Needs conservative patch policy |
| `heading-order` | guided | `GuidedOnlyStrategy` | manual | Semantic/content intent dependent |
| `label` | guided | `GuidedOnlyStrategy` | manual | Often requires visible label UX decisions |
| `document-title` | guided | `GuidedOnlyStrategy` | manual | Out of Bricks element patch scope |
| `html-has-lang` | guided | `GuidedOnlyStrategy` | manual | Site/theme scope |

## Next Rules to Promote (Priority Order)

1. `button-name` (fixture + scenario test)
2. `aria-label` (fixture + scenario test)
3. `input-image-alt` (fixture + scenario test)
4. `aria-labelledby` (validator hardening first)
5. `aria-hidden-focus` (conservative policy + rollback checks)

## Acceptance Criteria for Promoting a Rule to Auto-Fix

- At least one deterministic fixture exists
- Scenario test passes (target mapping + validator + apply)
- Manual Bricks test confirms patch persists
- Re-scan shows issue resolved or improved
- No forbidden keys/paths are touched

