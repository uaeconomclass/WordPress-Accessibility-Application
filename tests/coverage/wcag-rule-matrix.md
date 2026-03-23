# WCAG / axe Rule Coverage Matrix (Working Copy)

Use this file as the team's live checklist for what is implemented and verified.

Status values:

- `detect-only`
- `guided`
- `auto-fix`
- `auto-fix (conditional)`
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
| `image-alt` | auto-fix | `ImageAltFixStrategy` (planned) / current generic | fixture, unit, integration, manual | Scenario unit test covers mapping + validator + `settings.altText` patch path |
| `input-image-alt` | auto-fix | `InputImageAltFixStrategy` (planned) / current generic | fixture, unit, manual | Current real Bricks linked-image fixture surfaces as `link-name`; keep this rule in the broader strategy set, but do not treat it as a separate live page-level detector today |
| `link-name` | auto-fix | `LinkNameFixStrategy` (planned) / current generic | fixture, unit, manual | Scenario unit test covers selector + fallback mapping and `aria-label` patch path |
| `button-name` | auto-fix | `ButtonNameFixStrategy` (planned) / current generic | fixture, unit, manual | Scenario unit test covers mapping + validator + apply; confirm more Bricks variants |
| `frame-title` | auto-fix | `FrameTitleFixStrategy` (planned) / current generic | fixture, unit, manual | Scenario unit test covers `title` attribute patch |
| `color-contrast` | auto-fix | `ColorContrastFixStrategy` (planned) / current generic | fixture, unit, manual | Scenario unit test covers scoped `_cssCustom` apply + reject on global selector |
| `aria-label` | auto-fix | `AriaLabelFixStrategy` (planned) / current generic | fixture, unit, manual | Scenario unit test covers mapping + validator + apply; attribute allowlist enforced |
| `aria-labelledby` | auto-fix (conditional) | `AriaLabelledByFixStrategy` (planned) | unit (payload-guard), fixture (pending), manual | Structurally allowed by validator; still needs ID existence validation |
| `aria-hidden-focus` | auto-fix (conditional) | `AriaHiddenFocusFixStrategy` (planned) | unit (payload-guard), fixture (pending), manual | Structurally allowed by validator; still needs conservative patch policy |
| `heading-order` | guided | `GuidedOnlyStrategy` | manual | Semantic/content intent dependent |
| `label` | guided | `GuidedOnlyStrategy` | manual | Often requires visible label UX decisions |
| `document-title` | guided | `GuidedOnlyStrategy` | manual | Out of Bricks element patch scope |
| `html-has-lang` | guided | `GuidedOnlyStrategy` | manual | Site/theme scope |

## Next Rules to Promote (Priority Order)

1. `aria-labelledby` (validator hardening first)
2. `aria-hidden-focus` (conservative policy + rollback checks)
3. `link-name` (add more Bricks variants beyond current selector+text-basic coverage)
4. `button-name` (add more Bricks variants beyond current button path)
5. `color-contrast` (manual smoke on real Bricks variants + reduce false-positive/noise expectations)

## Acceptance Criteria for Promoting a Rule to Auto-Fix

- At least one deterministic fixture exists
- Scenario test passes (target mapping + validator + apply)
- Manual Bricks test confirms patch persists
- Re-scan shows issue resolved or improved
- No forbidden keys/paths are touched
