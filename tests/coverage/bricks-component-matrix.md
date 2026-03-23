# Bricks Component Coverage Matrix (Working Copy)

Use this file to track which Bricks component families have deterministic fixture coverage and whether they are currently intended for `auto-fix`, `guided`, or future work.

Detailed next-wave scenario planning lives in `tests/coverage/seed-scenario-backlog.md`.

Status values:

- `seeded`
- `partial`
- `planned`

Strategy values:

- `auto-fix`
- `guided-only`
- `flagged`

## Current Component Coverage

Current detectability snapshot from `tests/coverage/seed-detectability-report.md`:

- `27/37` scenarios are `scan-ready`
- `9/37` scenarios are `auto-fix-ready`
- strongest current auto-fix set: `image-alt-basic`, `link-name-inline`, `link-name-button`, `frame-title-inline`, `button-name-empty`, `button-icon-only`, `aria-label-icon`, `logo-image-alt`, `logo-linked-image`

| Component Family | Status | Current Strategy | Seeded Scenarios | Notes |
|---|---|---|---|---|
| `Heading` | seeded | guided-only | `heading-order-skip` | Good starter coverage for semantic heading issues |
| `Basic Text / Rich Text` | seeded | auto-fix, guided-only, flagged | `link-name-inline`, `color-contrast-inline`, `frame-title-inline`, `label-missing`, `aria-hidden-focus-inline` | Core fallback and inline-HTML coverage base |
| `Button` | seeded | auto-fix | `button-name-empty`, `button-icon-only`, `link-name-button`, `input-image-alt-banner` | `button-name-empty`, `button-icon-only`, and `link-name-button` are now scan/auto-fix ready; `input-image-alt-banner` still does not trigger in live Bricks render |
| `Icon` | partial | auto-fix, flagged | `aria-label-icon`, `aria-labelledby-missing` | `aria-label-icon` now renders and triggers `link-name`; `aria-labelledby-missing` still needs stricter ID/reference validation |
| `Image` | partial | auto-fix | `image-alt-basic`, `input-image-alt-banner`, `gallery-image-alt-grid` | `image-alt-basic` works; real Bricks image/gallery variants currently normalize to decorative `alt=\"\"` and do not trigger expected rules |
| `Video / Embed` | seeded | auto-fix, guided-only | `frame-title-inline`, `code-embed-frame-title` | Embed/code edge cases still need noise control |
| `Accordion` | partial | guided-only | `accordion-structure` | Real Bricks accordion now renders and is scan-ready via `heading-order`; still needs a stronger structural failure case |
| `Tabs` | seeded | guided-only | `tabs-structure` | Real tabs-nested tree now renders and is scan-ready through a live color-contrast issue in the tab menu |
| `Form` | partial | guided-only | `label-missing`, `form-label-required`, `form-checkbox-group`, `form-radio-group` | Three scenarios are scan-ready; `form-radio-group` still needs a real failing radiogroup case |
| `Map` | seeded | guided-only | `map-frame-title` | Starter fixture added via embedded map iframe |
| `Alert` | seeded | guided-only | `alert-live-region` | Starter fixture added for live-region semantics |
| `Countdown` | seeded | guided-only | `countdown-announcement` | Starter fixture added for time-based announcements |
| `Counter` | seeded | guided-only | `counter-meaning` | Starter fixture added for meaningful metric labeling |
| `Pricing Tables` | seeded | guided-only | `pricing-table-structure` | Starter fixture added for heading/list semantics |
| `Progress Bar` | seeded | guided-only | `progressbar-name` | Real Bricks progress-bar now renders and is scan-ready through a live color-contrast issue on the label |
| `Pie Chart` | seeded | guided-only | `piechart-summary` | Starter fixture added for chart alternative text guidance |
| `Team Members` | partial | guided-only | `team-members-profile` | Current team-member seed is scan-ready via heading structure, but not yet via image-alt |
| `Testimonials` | partial | guided-only | `testimonials-quote` | Seed exists, but current render does not yet produce the expected blockquote/image rule pair |
| `Logo` | seeded | auto-fix | `logo-image-alt`, `logo-linked-image` | Real Bricks logo component is now auto-fix ready for `logo-image-alt`; linked variant stays useful for link-name flow |
| `Gallery` | partial | auto-fix | `gallery-image-alt-grid` | Seed exists, but real Bricks gallery render currently normalizes images to decorative `alt=\"\"` |
| `Audio` | partial | guided-only | `audio-controls` | Seed exists, but the current audio scenario does not yet yield a stable axe rule |
| `Carousel` | partial | guided-only | `carousel-structure`, `carousel-controls` | Controls variant is scan-ready; structural carousel seed still does not render a detectable issue |
| `Slider` | seeded | guided-only | `carousel-structure`, `carousel-controls` | Shared starter fixtures with carousel family |
| `SVG` | seeded | flagged | `svg-accessible-name` | Starter fixture added for future accessible-name and title strategies |
| `Code / Embed` | seeded | guided-only | `code-embed-frame-title` | Mostly useful for editor noise and fallback handling |
| `Site Scope` | partial | guided-only | `document-title-site-scope`, `html-has-lang-site-scope` | Intentionally outside `#brx-content` page-level sweep; useful as out-of-scope controls, not scan-ready fixtures |

## Next Component Families To Seed

1. `Real Bricks Native Variants Per Family`
2. `Safe Auto-Fix Eligibility By Component`
3. `Scenario Re-Scan Assertions`
4. `Component-Specific Claude Prompt Packs`
5. `Noise/False-Positive Fixtures`
6. `False-Negative / Edge-Case Families`
7. `Prod-Parity Content Packs`

## Acceptance Criteria For A Component Family

- At least one deterministic fixture page exists
- The fixture maps to one or more explicit WCAG/axe rules
- The intended strategy is declared (`auto-fix`, `guided-only`, or `flagged`)
- The family is visible in the seed catalog and can be reseeded on demand
- A manual Bricks smoke test has been run at least once for that family
