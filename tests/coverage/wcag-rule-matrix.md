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
| `input-image-alt` | auto-fix | `InputImageAltFixStrategy` (planned) / current generic | fixture, unit, manual | Scenario unit test covers `settings.altText` patch path |
| `link-name` | auto-fix | `LinkNameFixStrategy` (planned) / current generic | fixture, unit (finder fallback), manual | Often via `aria-label` |
| `button-name` | auto-fix | `ButtonNameFixStrategy` (planned) / current generic | fixture, unit, manual | Scenario unit test covers mapping + validator + apply; confirm more Bricks variants |
| `frame-title` | auto-fix | `FrameTitleFixStrategy` (planned) / current generic | fixture, unit, manual | Scenario unit test covers `title` attribute patch |
| `color-contrast` | auto-fix | `ColorContrastFixStrategy` (planned) / current generic | fixture, unit (validator), manual | `_cssCustom` path constrained by validator |
| `aria-label` | auto-fix | `AriaLabelFixStrategy` (planned) / current generic | fixture, unit, manual | Scenario unit test covers mapping + validator + apply; attribute allowlist enforced |
| `aria-labelledby` | auto-fix (flagged) | `AriaLabelledByFixStrategy` (planned) | fixture (pending), manual | Needs ID existence validation |
| `aria-hidden-focus` | auto-fix (flagged) | `AriaHiddenFocusFixStrategy` (planned) | fixture (pending), manual | Needs conservative patch policy |
| `heading-order` | guided | `GuidedOnlyStrategy` | manual | Semantic/content intent dependent |
| `label` | guided | `GuidedOnlyStrategy` | manual | Often requires visible label UX decisions |
| `document-title` | guided | `GuidedOnlyStrategy` | manual | Out of Bricks element patch scope |
| `html-has-lang` | guided | `GuidedOnlyStrategy` | manual | Site/theme scope |

## Next Rules to Promote (Priority Order)

1. `aria-labelledby` (validator hardening first)
2. `aria-hidden-focus` (conservative policy + rollback checks)
3. `image-alt` (scenario test for parity with `input-image-alt`)
4. `color-contrast` (scenario fixture + guarded patch examples)
5. `link-name` (scenario test for multiple Bricks variants)

## Acceptance Criteria for Promoting a Rule to Auto-Fix

- At least one deterministic fixture exists
- Scenario test passes (target mapping + validator + apply)
- Manual Bricks test confirms patch persists
- Re-scan shows issue resolved or improved
- No forbidden keys/paths are touched
