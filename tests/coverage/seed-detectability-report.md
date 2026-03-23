# Seed Detectability Report

Generated: 2026-03-23T16:10:41.274Z

| Scenario | Strategy | Expected Rules | Found Rules | Detectable | Scan Ready | Auto-fix Ready | Note |
| --- | --- | --- | --- | --- | --- | --- | --- |
| image-alt-basic | auto-fix | image-alt | image-alt | yes | yes | yes | Detected inside page-level content and eligible for auto-fix testing. |
| input-image-alt-banner | auto-fix | input-image-alt | — | no | no | no | Expected rule did not appear inside #brx-content. |
| link-name-inline | auto-fix | link-name | link-name | yes | yes | yes | Detected inside page-level content and eligible for auto-fix testing. |
| link-name-button | auto-fix | link-name | link-name | yes | yes | yes | Detected inside page-level content and eligible for auto-fix testing. |
| color-contrast-inline | guided-only | color-contrast | color-contrast | yes | yes | no | Detected inside page-level content. |
| frame-title-inline | auto-fix | frame-title | frame-title, frame-tested | yes | yes | yes | Detected inside page-level content and eligible for auto-fix testing. |
| button-name-empty | auto-fix | button-name | button-name | yes | yes | yes | Detected inside page-level content and eligible for auto-fix testing. |
| button-icon-only | auto-fix | button-name, aria-label | button-name | yes | yes | yes | Detected inside page-level content and eligible for auto-fix testing. |
| aria-label-icon | auto-fix | aria-label, link-name | link-name | yes | yes | yes | Detected inside page-level content and eligible for auto-fix testing. |
| aria-labelledby-missing | flagged | aria-labelledby | link-name | no | no | no | Expected rule did not appear inside #brx-content. |
| aria-hidden-focus-inline | flagged | aria-hidden-focus | aria-hidden-focus, color-contrast | yes | yes | no | Detected inside page-level content. |
| heading-order-skip | guided-only | heading-order | heading-order | yes | yes | no | Detected inside page-level content. |
| label-missing | guided-only | label | label | yes | yes | no | Detected inside page-level content. |
| document-title-site-scope | guided-only | document-title | — | no | no | no | Expected rule did not appear inside #brx-content. |
| html-has-lang-site-scope | guided-only | html-has-lang | — | no | no | no | Expected rule did not appear inside #brx-content. |
| gallery-image-alt-grid | auto-fix | image-alt | — | no | no | no | Expected rule did not appear inside #brx-content. |
| form-label-required | guided-only | label, aria-label | label | yes | yes | no | Detected inside page-level content. |
| form-checkbox-group | guided-only | label, fieldset | label | yes | yes | no | Detected inside page-level content. |
| form-radio-group | guided-only | label, radiogroup | color-contrast | no | no | no | Expected rule did not appear inside #brx-content. |
| accordion-structure | guided-only | aria-required-children, heading-order | heading-order | yes | yes | no | Detected inside page-level content. |
| tabs-structure | guided-only | color-contrast, button-name, aria-required-children, aria-required-parent | color-contrast | yes | yes | no | Detected inside page-level content. |
| progressbar-name | guided-only | color-contrast, aria-progressbar-name | color-contrast | yes | yes | no | Detected inside page-level content. |
| carousel-structure | guided-only | aria-required-children, scrollable-region-focusable | — | no | no | no | Expected rule did not appear inside #brx-content. |
| carousel-controls | guided-only | button-name, aria-label | button-name | yes | yes | no | Detected inside page-level content. |
| svg-accessible-name | flagged | svg-img-alt, aria-label | svg-img-alt | yes | yes | no | Detected inside page-level content. |
| logo-image-alt | auto-fix | image-alt | image-alt, link-name | yes | yes | yes | Detected inside page-level content and eligible for auto-fix testing. |
| logo-linked-image | auto-fix | image-alt, link-name | link-name | yes | yes | yes | Detected inside page-level content and eligible for auto-fix testing. |
| map-frame-title | guided-only | frame-title | frame-title, frame-tested | yes | yes | no | Detected inside page-level content. |
| alert-live-region | guided-only | aria-required-attr, aria-valid-attr-value | aria-valid-attr-value | yes | yes | no | Detected inside page-level content. |
| audio-controls | guided-only | audio-caption, aria-label | — | no | no | no | Expected rule did not appear inside #brx-content. |
| pricing-table-structure | guided-only | heading-order, listitem | heading-order | yes | yes | no | Detected inside page-level content. |
| piechart-summary | guided-only | svg-img-alt, aria-label | svg-img-alt | yes | yes | no | Detected inside page-level content. |
| team-members-profile | guided-only | image-alt, heading-order | heading-order | yes | yes | no | Detected inside page-level content. |
| testimonials-quote | guided-only | image-alt, blockquote | — | no | no | no | Expected rule did not appear inside #brx-content. |
| countdown-announcement | guided-only | aria-live-region, aria-valid-attr-value | aria-valid-attr-value | yes | yes | no | Detected inside page-level content. |
| counter-meaning | guided-only | aria-label, color-contrast | color-contrast | yes | yes | no | Detected inside page-level content. |
| code-embed-frame-title | guided-only | frame-title | — | no | no | no | Expected rule did not appear inside #brx-content. |

Scan-ready: 27/37
Auto-fix-ready: 9/37

