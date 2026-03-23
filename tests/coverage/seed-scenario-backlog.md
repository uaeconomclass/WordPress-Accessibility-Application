# Seed Scenario Backlog

Purpose: track the next wave of deterministic Bricks fixture scenarios needed to move from "broad family coverage" to "strong engineering coverage" for scan, guided-fix, and auto-fix validation.

Status values:

- `next`
- `later`

Priority buckets:

- `P1` safe auto-fix proof
- `P2` native Bricks/component variants
- `P3` noise / edge-case / false-positive control

## P1 — Safe Auto-Fix Proof Scenarios

These scenarios should prove that a safe rule/component combination can be:

1. seeded
2. detected
3. auto-fixed
4. re-scanned successfully

| Priority | Scenario | Component(s) | Rule(s) | Goal | Status |
|---|---|---|---|---|---|
| `P1` | `image-alt-linked-image` | `image`, `link` | `image-alt` | Prove alt-text auto-fix on linked image variant | `next` |
| `P1` | `image-alt-gallery-third-item` | `gallery`, `image` | `image-alt` | Prove auto-fix on repeated gallery child elements | `next` |
| `P1` | `button-name-cta-textless` | `button` | `button-name` | Prove accessible-name patch for empty CTA button | `next` |
| `P1` | `button-name-icon-only-secondary` | `button`, `icon` | `button-name`, `aria-label` | Prove icon-only button labeling path | `next` |
| `P1` | `link-name-card-link` | `text-basic`, `link` | `link-name` | Prove discernible-name patch for card-style link | `next` |
| `P1` | `link-name-image-link` | `image`, `link` | `link-name`, `image-alt` | Prove linked-image naming strategy | `next` |
| `P1` | `frame-title-map-embed` | `map`, `embed` | `frame-title` | Prove title patch for iframe embed | `next` |
| `P1` | `frame-title-video-embed` | `video`, `embed` | `frame-title` | Prove title patch for video embed | `next` |
| `P1` | `aria-label-icon-link` | `icon`, `link` | `aria-label` | Prove safe aria-label patch for icon link | `next` |
| `P1` | `aria-label-social-icon` | `icon`, `link` | `aria-label` | Prove repeated social icon labeling | `next` |
| `P1` | `logo-image-home-link` | `logo`, `image`, `link` | `image-alt`, `link-name` | Prove branding/logo safe auto-fix path | `next` |
| `P1` | `input-image-alt-submit-banner` | `image`, `button` | `input-image-alt` | Prove image-submit CTA variant | `later` |

## P2 — Native Bricks / Component Variant Scenarios

These scenarios increase fidelity so we are not relying only on generic text-basic HTML fixtures.

| Priority | Scenario | Component(s) | Rule(s) | Goal | Status |
|---|---|---|---|---|---|
| `P2` | `heading-order-native-stack` | `heading` | `heading-order` | Real multi-heading sequence variant | `next` |
| `P2` | `rich-text-inline-link-name` | `rich-text` | `link-name` | Rich text variant, not only text-basic | `next` |
| `P2` | `rich-text-inline-button-name` | `rich-text` | `button-name` | Rich text/button semantics variant | `later` |
| `P2` | `form-textarea-label` | `form` | `label` | Textarea-specific labeling variant | `next` |
| `P2` | `form-select-label` | `form` | `label` | Select/dropdown labeling variant | `next` |
| `P2` | `form-submit-name` | `form`, `button` | `button-name` | Submit button naming inside form flow | `next` |
| `P2` | `accordion-multi-item-state` | `accordion` | `aria-required-children` | More realistic accordion item/state variant | `next` |
| `P2` | `tabs-active-state` | `tabs` | `aria-required-parent`, `aria-required-children` | Add active/inactive tab state variant | `next` |
| `P2` | `carousel-dots-navigation` | `carousel`, `slider` | `button-name` | Navigation dots/controls variant | `next` |
| `P2` | `carousel-autoplay-pause` | `carousel`, `slider` | `button-name`, `aria-label` | Pause/play control variant | `later` |
| `P2` | `progressbar-labeled-inline` | `progress-bar` | `aria-progressbar-name` | Labeled progress variant | `later` |
| `P2` | `pricing-table-feature-list` | `pricing-tables` | `listitem`, `heading-order` | Native pricing card list semantics variant | `later` |
| `P2` | `team-members-multiple-cards` | `team-members`, `image`, `heading` | `image-alt`, `heading-order` | Repeated team-card grid variant | `later` |
| `P2` | `testimonials-carousel` | `testimonials`, `carousel` | `blockquote`, `button-name` | Testimonial slider variant | `later` |

## P3 — Noise / Edge / False-Positive Control Scenarios

These scenarios protect us from misleading scan results and broken auto-fix scope.

| Priority | Scenario | Component(s) | Rule(s) | Goal | Status |
|---|---|---|---|---|---|
| `P3` | `template-bleed-header-link` | `template`, `link` | `link-name` | Ensure template/header issue does not count as page-level issue | `next` |
| `P3` | `global-element-nonpage-image` | `global`, `image` | `image-alt` | Ensure non-page global element is filtered out | `next` |
| `P3` | `code-placeholder-invalid-signature` | `code` | `color-contrast` | Ensure editor placeholder noise is excluded from scoring | `next` |
| `P3` | `render-wrapper-noneditable-node` | `wrapper` | `aria-label` | Ensure noneditable wrapper node does not show auto-fix affordance | `next` |
| `P3` | `conditional-aria-labelledby-missing-target` | `icon`, `text-basic` | `aria-labelledby` | Validate guarded/conditional rule remains non-auto-apply without target ID | `next` |
| `P3` | `conditional-aria-hidden-focus-layout-risk` | `text-basic` | `aria-hidden-focus` | Validate guarded rule is not blindly auto-applied | `next` |
| `P3` | `svg-decorative-vs-informative` | `svg` | `svg-img-alt` | Distinguish decorative SVG from informative SVG | `later` |
| `P3` | `contrast-acss-class-vs-inline-style` | `text-basic`, `acss` | `color-contrast` | Compare ACSS utility-driven contrast vs inline-style case | `later` |
| `P3` | `duplicate-id-cross-component` | `tabs`, `accordion` | `duplicate-id` | Confirm guided-only handling of multi-element coordination | `later` |
| `P3` | `site-scope-lang-vs-page-scope` | `site-scope` | `html-has-lang` | Confirm site-scope issue is cataloged but not page-patched | `later` |

## Recommended Next 10 To Build

1. `image-alt-linked-image`
2. `button-name-cta-textless`
3. `link-name-card-link`
4. `frame-title-map-embed`
5. `aria-label-icon-link`
6. `heading-order-native-stack`
7. `form-textarea-label`
8. `accordion-multi-item-state`
9. `template-bleed-header-link`
10. `code-placeholder-invalid-signature`

## Definition Of "Good Enough"

Call the seed bed "good enough" for the next engineering phase when:

- all main spec component families have at least one deterministic seeded scenario
- top safe auto-fix families have at least two meaningful variants each
- core edge/noise cases are represented
- at least one re-scan assertion path exists for each safe auto-fix family
