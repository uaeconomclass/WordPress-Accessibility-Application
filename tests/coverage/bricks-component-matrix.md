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
| `Button` | seeded | auto-fix | `button-name-empty`, `link-name-button`, `input-image-alt-banner` | Strong current auto-fix target family |
| `Icon` | seeded | auto-fix, flagged | `aria-label-icon`, `aria-labelledby-missing` | Needs stricter ID/reference validation for flagged cases |
| `Image` | seeded | auto-fix | `image-alt-basic`, `input-image-alt-banner`, `gallery-image-alt-grid` | Strongest deterministic family so far |
| `Video / Embed` | seeded | auto-fix, guided-only | `frame-title-inline`, `code-embed-frame-title` | Embed/code edge cases still need noise control |
| `Accordion` | planned | guided-only | none | Need real Bricks accordion fixture + keyboard/ARIA expectations |
| `Tabs` | planned | guided-only | none | Needs tab order, roles, and aria-selected coverage |
| `Form` | seeded | guided-only | `label-missing`, `form-label-required` | Good starting point, still light on real Bricks form field variants |
| `Map` | planned | guided-only | none | Likely iframe/title + alternative text guidance |
| `Alert` | planned | guided-only | none | Needs live-region semantics decisions |
| `Countdown` | planned | guided-only | none | Time-sensitive announcements and pause/stop guidance |
| `Counter` | planned | guided-only | none | Likely semantic/meaningful text guidance |
| `Pricing Tables` | planned | guided-only | none | Usually structural semantics and heading/list associations |
| `Progress Bar` | planned | guided-only | none | Needs ARIA/progress semantics fixture |
| `Pie Chart` | planned | guided-only | none | Needs text alternative / data summary coverage |
| `Team Members` | planned | guided-only | none | Mostly image alt + heading/link semantics combinations |
| `Testimonials` | planned | guided-only | none | Usually quote/citation structure and image alt combinations |
| `Logo` | planned | auto-fix | none | Could reuse image-alt family with branding semantics |
| `Gallery` | seeded | auto-fix | `gallery-image-alt-grid` | Good initial image-heavy coverage |
| `Audio` | planned | guided-only | none | Needs transcript / control-label guidance |
| `Carousel` | planned | guided-only | none | Keyboard, pause, and announcement semantics |
| `Slider` | planned | guided-only | none | Similar to carousel, likely guided-only first |
| `SVG` | planned | flagged | none | Candidate for aria-label/title strategies after validation rules harden |
| `Code / Embed` | seeded | guided-only | `code-embed-frame-title` | Mostly useful for editor noise and fallback handling |
| `Site Scope` | seeded | guided-only | `document-title-site-scope`, `html-has-lang-site-scope` | Not a Bricks patch target, but important to keep in catalog |

## Next Component Families To Seed

1. `Accordion`
2. `Tabs`
3. `Progress Bar`
4. `Carousel`
5. `SVG`
6. `Logo`
7. `Map`

## Acceptance Criteria For A Component Family

- At least one deterministic fixture page exists
- The fixture maps to one or more explicit WCAG/axe rules
- The intended strategy is declared (`auto-fix`, `guided-only`, or `flagged`)
- The family is visible in the seed catalog and can be reseeded on demand
- A manual Bricks smoke test has been run at least once for that family
