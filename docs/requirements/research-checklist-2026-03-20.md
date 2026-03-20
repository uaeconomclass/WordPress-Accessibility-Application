# Research Checklist: Accessibility Plugin Requirements for Bricks & AutomaticCSS

Source requirements:
- Google Doc: `Accessibility Plugin Requirements for Bricks & AutomaticCSS`
- Last updated in source doc: `2025-09-29`

Purpose:
- walk through the requirements systematically
- assess current implementation coverage
- assess code quality and refactor need
- assess Claude auto-fix feasibility for Phase 2

Status values:
- `Done`
- `Partial`
- `Missing`
- `Unclear`

Use this evidence format:
- file/class: `classes/AI.php`
- UI surface: `Pages list`, `Dashboard widget`, `Bricks panel`
- test evidence: `tests/unit/...`, `tests/integration/...`, `tests/e2e/...`

Suggested review order:
1. Core Functional Requirements
2. Technical Requirements
3. Score / grading clarifications
4. Component-by-component WCAG matrix
5. Claude auto-fix feasibility
6. Executive summary and recommendation

## 1. Executive Summary

Fill this only after completing the checklist.

- Overall state:
- Main strengths:
- Main gaps:
- Biggest technical risks:
- Recommendation for Phase 2:

## 2. Core Functional Requirements

| ID | Requirement | Status | Evidence | Risk | Recommendation |
|---|---|---|---|---|---|
| F1 | Accessibility scanning exists and runs automatically/on demand |  |  |  |  |
| F1.1 | Uses a reliable scanning engine (`axe-core`, Lighthouse, equivalent) |  |  |  |  |
| F1.2 | Results are aligned to WCAG 2.x criteria |  |  |  |  |
| F1.3 | Results reflect WCAG 2.2 expectations |  |  |  |  |
| F1.4 | Distinguishes auto-detectable issues vs human-review issues |  |  |  |  |
| F1.5 | Findings are actionable (alt, contrast, ARIA, labels, headings, etc.) |  |  |  |  |
| F2 | Page list indicators exist in WP admin Pages screen |  |  |  |  |
| F2.1 | Uses pages column hooks appropriately |  |  |  |  |
| F2.2 | Green/yellow/red status indicator exists |  |  |  |  |
| F2.3 | Indicator links to detailed report |  |  |  |  |
| F2.4 | Scan results are stored per page |  |  |  |  |
| F2.5 | Indicator refreshes after update or on-demand scan |  |  |  |  |
| F3 | AI-powered assistance exists |  |  |  |  |
| F3.1 | Guided Fix mode exists |  |  |  |  |
| F3.1.1 | Guided fix uses page issues + Bricks/ACSS context |  |  |  |  |
| F3.1.2 | Guided fix returns step-by-step instructions |  |  |  |  |
| F3.1.3 | Instructions use Bricks terminology |  |  |  |  |
| F3.1.4 | Instructions reference WCAG/W3C where appropriate |  |  |  |  |
| F3.2 | Automated Fix mode exists |  |  |  |  |
| F3.2.1 | Auto-fix requests structured JSON from Claude |  |  |  |  |
| F3.2.2 | JSON is limited to Bricks settings / ACSS adjustments |  |  |  |  |
| F3.2.3 | Plugin applies changes via Bricks-safe APIs/data model |  |  |  |  |
| F3.2.4 | Raw theme code is not edited |  |  |  |  |
| F3.2.5 | Auto-fix creates revision/snapshot before apply |  |  |  |  |
| F3.2.6 | Changelog records element/setting/before-after |  |  |  |  |
| F4 | Rollback and revision history exist |  |  |  |  |
| F4.1 | AI changes can be reverted |  |  |  |  |
| F4.2 | Uses native WP revisions rather than only custom snapshots |  |  |  |  |
| F4.3 | UI links to revision/restore flow |  |  |  |  |
| F4.4 | Built-in changelog is visible to admins |  |  |  |  |
| F5 | Dashboard reporting exists |  |  |  |  |
| F5.1 | Dashboard widget exists |  |  |  |  |
| F5.2 | Widget shows overall site accessibility score |  |  |  |  |
| F5.3 | Widget shows pages with outstanding issues |  |  |  |  |
| F5.4 | Widget groups issues by severity |  |  |  |  |
| F5.5 | Widget provides recommended actions |  |  |  |  |
| F5.6 | Widget updates after scan/fix activity |  |  |  |  |

## 3. Technical Requirements

| ID | Requirement | Status | Evidence | Risk | Recommendation |
|---|---|---|---|---|---|
| T1 | Compatible with WordPress 6.0+ |  |  |  |  |
| T1.1 | Uses WordPress hooks/APIs correctly |  |  |  |  |
| T1.2 | Uses secure settings storage/sanitization |  |  |  |  |
| T1.3 | Scans avoid blocking page requests/timeouts |  |  |  |  |
| T1.4 | Uses WP Cron/AJAX/batching where needed |  |  |  |  |
| T2 | Bricks Builder integration is safe and direct |  |  |  |  |
| T2.1 | Updates go through Bricks data model/APIs |  |  |  |  |
| T2.2 | No direct unsafe HTML/code injection for fixes |  |  |  |  |
| T2.3 | AutomaticCSS class usage aligns with ACSS intent |  |  |  |  |
| T2.4 | Bricks/ACSS compatibility is evidence-backed rather than assumed |  |  |  |  |
| T3 | User can select WCAG compliance level |  |  |  |  |
| T3.1 | Scan behavior changes based on chosen level |  |  |  |  |
| T3.2 | Recommendations reflect chosen level |  |  |  |  |
| T3.3 | Feature-to-WCAG mapping exists or can be produced from code/docs |  |  |  |  |
| T4 | Accessibility API / Core-AAM depth is covered |  |  |  |  |
| T4.1 | ARIA roles/states issues are detected reliably |  |  |  |  |
| T4.2 | Keyboard support issues are covered or explicitly limited |  |  |  |  |
| T4.3 | Manual checks are clearly identified where automation is insufficient |  |  |  |  |
| T5 | Claude API integration is secure and production-safe |  |  |  |  |
| T5.1 | API key is stored securely and not exposed client-side |  |  |  |  |
| T5.2 | Only authorized users can invoke AI features |  |  |  |  |
| T5.3 | Usage limits per customer exist |  |  |  |  |
| T5.4 | Requests are non-blocking / async / queued |  |  |  |  |
| T5.5 | API errors are handled gracefully |  |  |  |  |
| T5.6 | AI changes require confirmation before save |  |  |  |  |
| T5.7 | AI output is validated before apply |  |  |  |  |
| T6 | Performance and security basics are covered |  |  |  |  |
| T6.1 | Minimal scripts/styles are loaded |  |  |  |  |
| T6.2 | Scanning is on-demand, on update, or otherwise controlled |  |  |  |  |
| T6.3 | Nonces and capability checks are present |  |  |  |  |
| T6.4 | AI responses and external inputs are sanitized/validated |  |  |  |  |
| T6.5 | Output escaping and WP security practices are followed |  |  |  |  |

## 4. Requirement Clarification: Letter-Grading Score System

| ID | Requirement | Status | Evidence | Risk | Recommendation |
|---|---|---|---|---|---|
| S1 | Page starts at score `100` |  |  |  |  |
| S2 | Every issue reduces score by exactly `5` points |  |  |  |  |
| S3 | Severity/type does not change deduction amount |  |  |  |  |
| S4 | Single instance = single issue |  |  |  |  |
| S5 | Multiple instances of the same type are counted individually |  |  |  |  |
| S6 | Score is clamped to `0` minimum |  |  |  |  |
| S7 | Grade `A` is `>= 95` |  |  |  |  |
| S8 | Grade `B` is `>= 85` |  |  |  |  |
| S9 | Grade `C` is `>= 70` |  |  |  |  |
| S10 | Grade `D` is `>= 50` |  |  |  |  |
| S11 | Behavior below `50` is consistent and documented |  |  |  |  |
| S12 | `incomplete` / needs-review issues are treated consistently |  |  |  |  |
| S13 | Score shown in UI matches persisted score/meta |  |  |  |  |

Notes:
- This section is worth verifying directly in code and tests, not only via UI.
- Watch for ambiguity below score `50`, since the source doc says minimum is `0` and `D`.

## 5. Component Matrix: Detection / Guided Fix / Auto-Fix

Use these values:
- `Detect`: `Yes / Partial / No`
- `Guided`: `Yes / Partial / No`
- `Auto-fix`: `Safe / Risky / No`

| Req ID | Component | WCAG Level | Requirement | Success Criterion | Detect | Guided | Auto-fix | Evidence | Notes |
|---|---|---|---|---|---|---|---|---|---|
| 2.1 | Heading | AA | Descriptive heading text | 2.4.6 |  |  |  |  |  |
| 2.2 | Basic Text | AA | Text color contrast | 1.4.3 |  |  |  |  |  |
| 2.3 | Rich Text | A | Semantic formatting | 1.3.1 |  |  |  |  |  |
| 2.4 | Button | A | Accessible button label | 4.1.2 |  |  |  |  |  |
| 2.5 | Icon | A | Alt text or aria-hidden | 1.1.1 |  |  |  |  |  |
| 2.6 | Image | A | Alt attribute | 1.1.1 |  |  |  |  |  |
| 2.7 | Video | A | Captions for video | 1.2.2 |  |  |  |  |  |
| 2.8 | Divider | N/A | None applicable | N/A |  |  |  |  |  |
| 2.9 | Icon Box | A | Icon alternative text | 1.1.1 |  |  |  |  |  |
| 2.10 | Icon List | A | Decorative icons hidden | 1.1.1 |  |  |  |  |  |
| 2.11 | List | A | Semantic list structure | 1.3.1 |  |  |  |  |  |
| 2.12 | Accordion | A | ARIA roles and state | 4.1.2 |  |  |  |  |  |
| 2.13 | Accordion (Nestable) | A | Nested ARIA roles/state | 4.1.2 |  |  |  |  |  |
| 2.14 | Tabs | A | Tab roles and labels | 4.1.2 |  |  |  |  |  |
| 2.15 | Tabs (Nestable) | A | Nested tab roles | 4.1.2 |  |  |  |  |  |
| 2.16 | Form | A | Field labels | 3.3.2 |  |  |  |  |  |
| 2.17 | Map | A | Iframe title | 4.1.2 |  |  |  |  |  |
| 2.18 | Alert | A | Close button label | 4.1.2 |  |  |  |  |  |
| 2.19 | Anim. Typing | A | Pause moving text | 2.2.2 |  |  |  |  |  |
| 2.20 | Countdown | A | Adjustable time limit | 2.2.1 |  |  |  |  |  |
| 2.21 | Counter | AA | Announce dynamic updates | 4.1.3 |  |  |  |  |  |
| 2.22 | Pricing Tables | A | Table headers | 1.3.1 |  |  |  |  |  |
| 2.23 | Progress Bar | A | Progress value description | 4.1.2 |  |  |  |  |  |
| 2.24 | Pie Chart | A | Textual alternative | 1.1.1 |  |  |  |  |  |
| 2.25 | Team Members | A | Social link labels | 2.4.4 |  |  |  |  |  |
| 2.26 | Testimonials | A | Author image alt | 1.1.1 |  |  |  |  |  |
| 2.27 | Logo | A | Logo alt text | 1.1.1 |  |  |  |  |  |
| 2.28 | Facebook Page | A | Iframe title | 4.1.2 |  |  |  |  |  |
| 2.29 | Image Gallery | A | Alt text for images | 1.1.1 |  |  |  |  |  |
| 2.30 | Audio | A | Transcript for audio | 1.2.1 |  |  |  |  |  |
| 2.31 | Carousel | A | Pause on animation | 2.2.2 |  |  |  |  |  |
| 2.32 | Slider | A | Keyboard navigation | 2.1.1 |  |  |  |  |  |
| 3.33 | Slider (Nestable) | A | Slide indicator labels | 4.1.2 |  |  |  |  |  |
| 2.34 | SVG | A | SVG title/aria-label | 1.1.1 |  |  |  |  |  |

## 6. Claude Auto-Fix Feasibility Assessment

Use these values:
- `Feasibility`: `Safe subset / Guided only / Manual only`
- `Patchability`: `Single-element / Multi-element / Page-wide / Non-structural`
- `Confidence`: `High / Medium / Low`

| Issue Type / Component | Feasibility | Patchability | Confidence | Main Risk | Evidence | Notes |
|---|---|---|---|---|---|---|
| Missing alt text on image/logo/gallery/testimonial image |  |  |  |  |  |  |
| Link/button accessible name |  |  |  |  |  |  |
| Iframe title (map/facebook/embed) |  |  |  |  |  |  |
| Missing ARIA label on control |  |  |  |  |  |  |
| `aria-live` for dynamic counter |  |  |  |  |  |  |
| Decorative icon should be hidden |  |  |  |  |  |  |
| Heading tag correction |  |  |  |  |  |  |
| Heading hierarchy across page |  |  |  |  |  |  |
| Color contrast |  |  |  |  |  |  |
| Rich text semantic restructuring |  |  |  |  |  |  |
| Form labeling |  |  |  |  |  |  |
| Accordion ARIA/state |  |  |  |  |  |  |
| Tabs ARIA pattern |  |  |  |  |  |  |
| Video captions |  |  |  |  |  |  |
| Audio transcript |  |  |  |  |  |  |
| Carousel pause behavior |  |  |  |  |  |  |
| Slider keyboard support |  |  |  |  |  |  |
| Countdown timing adjustability |  |  |  |  |  |  |
| Progress bar semantics |  |  |  |  |  |  |
| SVG accessible name |  |  |  |  |  |  |

Suggested conclusion buckets:
- `Safe subset`: good Phase 2 auto-fix candidates
- `Guided only`: Claude can help, but plugin should not auto-apply
- `Manual only`: should not be delegated to one-click auto-fix

## 7. Code Quality / Refactor Review

| Area | Status | Evidence | Risk | Recommendation |
|---|---|---|---|---|
| Scan pipeline architecture |  |  |  |  |
| Score calculation logic |  |  |  |  |
| Pages/report/dashboard cohesion |  |  |  |  |
| AI orchestration (`AI.php`) |  |  |  |  |
| Claude client/error handling |  |  |  |  |
| AI response normalization |  |  |  |  |
| Bricks target element resolution |  |  |  |  |
| Patch validation |  |  |  |  |
| Patch application safety |  |  |  |  |
| Revision/rollback implementation |  |  |  |  |
| Settings/API key handling |  |  |  |  |
| WordPress capability/nonces/security |  |  |  |  |
| Test coverage and confidence |  |  |  |  |

Refactor priority notes:
- `Critical before Phase 2`:
- `High-value cleanup`:
- `Can wait`:

## 8. Test Coverage Review

| Test Area | Coverage | Evidence | Main Gap | Recommendation |
|---|---|---|---|---|
| Unit tests for Bricks element finding |  |  |  |  |
| Unit tests for patch validation |  |  |  |  |
| Unit tests for patch application |  |  |  |  |
| Integration tests for persistence/meta/revisions |  |  |  |  |
| E2E smoke for scan -> fix flows |  |  |  |  |
| Error-path testing for Claude failures |  |  |  |  |
| Unsupported-rule fallback coverage |  |  |  |  |

## 9. Final Recommendation

Fill this after the full review.

### Coverage summary
- Done:
- Partial:
- Missing:
- Unclear:

### Code quality summary
- Strong areas:
- Main technical debt:
- Refactor needed before expansion:

### Claude auto-fix summary
- Safe subset:
- Risky subset:
- No-go subset:

### Phase 2 recommendation
- `Proceed`
- `Proceed with constraints`
- `Do not proceed yet`

Why:
- 
