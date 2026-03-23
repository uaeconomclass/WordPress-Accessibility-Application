# Bricks Component Coverage Matrix (Working Copy)

Use this file to track which Bricks component families have deterministic fixture coverage and whether they are currently intended for `auto-fix`, `guided`, or future work.

Status values:

- `seeded`
- `partial`
- `planned`

Strategy values:

- `auto-fix`
- `guided-only`
- `flagged`

## Current Component Coverage

| Component Family | Status | Current Strategy | Seeded Scenarios | Notes |
|---|---|---|---|---|
| `Heading` | seeded | guided-only | `heading-order-skip` | Good starter coverage for semantic heading issues |
| `Basic Text / Rich Text` | seeded | auto-fix, guided-only, flagged | `link-name-inline`, `color-contrast-inline`, `frame-title-inline`, `label-missing`, `aria-hidden-focus-inline` | Core fallback and inline-HTML coverage base |
| `Button` | seeded | auto-fix | `button-name-empty`, `button-icon-only`, `link-name-button`, `input-image-alt-banner` | Strong current auto-fix target family with plain and icon-only variants |
| `Icon` | seeded | auto-fix, flagged | `aria-label-icon`, `aria-labelledby-missing` | Needs stricter ID/reference validation for flagged cases |
| `Image` | seeded | auto-fix | `image-alt-basic`, `input-image-alt-banner`, `gallery-image-alt-grid` | Strongest deterministic family so far |
| `Video / Embed` | seeded | auto-fix, guided-only | `frame-title-inline`, `code-embed-frame-title` | Embed/code edge cases still need noise control |
| `Accordion` | seeded | guided-only | `accordion-structure` | Initial family fixture added; still needs richer keyboard and state coverage |
| `Tabs` | seeded | guided-only | `tabs-structure` | Initial family fixture added; still needs tab order and selection-state coverage |
| `Form` | seeded | guided-only | `label-missing`, `form-label-required`, `form-checkbox-group`, `form-radio-group` | Better variant coverage across basic, checkbox, and radio group patterns |
| `Map` | seeded | guided-only | `map-frame-title` | Starter fixture added via embedded map iframe |
| `Alert` | seeded | guided-only | `alert-live-region` | Starter fixture added for live-region semantics |
| `Countdown` | seeded | guided-only | `countdown-announcement` | Starter fixture added for time-based announcements |
| `Counter` | seeded | guided-only | `counter-meaning` | Starter fixture added for meaningful metric labeling |
| `Pricing Tables` | seeded | guided-only | `pricing-table-structure` | Starter fixture added for heading/list semantics |
| `Progress Bar` | seeded | guided-only | `progressbar-name` | Initial progress semantics fixture added; still needs real re-scan validation |
| `Pie Chart` | seeded | guided-only | `piechart-summary` | Starter fixture added for chart alternative text guidance |
| `Team Members` | seeded | guided-only | `team-members-profile` | Starter fixture added for profile image and heading semantics |
| `Testimonials` | seeded | guided-only | `testimonials-quote` | Starter fixture added for quote/citation structure |
| `Logo` | seeded | auto-fix | `logo-image-alt`, `logo-linked-image` | Safe branding coverage for standalone and linked logo variants |
| `Gallery` | seeded | auto-fix | `gallery-image-alt-grid` | Good initial image-heavy coverage |
| `Audio` | seeded | guided-only | `audio-controls` | Starter fixture added for transcript/control guidance |
| `Carousel` | seeded | guided-only | `carousel-structure`, `carousel-controls` | Added controls variant for next/prev naming guidance |
| `Slider` | seeded | guided-only | `carousel-structure`, `carousel-controls` | Shared starter fixtures with carousel family |
| `SVG` | seeded | flagged | `svg-accessible-name` | Starter fixture added for future accessible-name and title strategies |
| `Code / Embed` | seeded | guided-only | `code-embed-frame-title` | Mostly useful for editor noise and fallback handling |
| `Site Scope` | seeded | guided-only | `document-title-site-scope`, `html-has-lang-site-scope` | Not a Bricks patch target, but important to keep in catalog |

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
