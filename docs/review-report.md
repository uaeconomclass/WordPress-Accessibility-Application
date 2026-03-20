# Accessibility Auditor Plugin — Code Review & Feasibility Report

**Prepared by:** Valentyn Moroz
**Date:** 2026-02-24
**Repo:** WordPress-Accessibility-Application (`feature/auto-fix` branch)
**Spec ref:** [Google Doc requirements](https://docs.google.com/document/d/1Zd2yxkuNsaz5LvZtkKe2kbfrOEos4JhZhQV0IshBHLc/)

---

## Executive Summary

The previous developers delivered a working foundation: axe-core scanning, page-level indicators, guided AI fixes, and a partial auto-fix implementation. The core architecture is sound, but the auto-fix flow had several critical bugs that prevented it from working end-to-end. Those have now been repaired. The plugin is not production-ready, but it is a viable base for Phase 2 work.

Auto-fix via Claude is **feasible for a defined subset of issue types** — specifically aria-labels, alt text, semantic tag corrections, and simple role attribute fixes. It is not feasible as a one-click "fix everything" solution for all 34 component types in the spec.

---

## Part 1 — Requirements Gap Analysis

### Core Feature Coverage

| Requirement | Status | Notes |
|---|---|---|
| Axe-core scanning (WCAG 2.x) | ✅ Done | See WCAG version note below |
| Page list status indicators | ✅ Done | Green/yellow/red/blue dot column |
| Link indicators to scan reports | ✅ Done | Dot links to aa-scan-report page |
| Guided Fix (Claude step-by-step) | ✅ Done | Returns formatted HTML steps |
| Auto-Fix (Claude JSON patch) | ✅ Fixed | Was broken; repaired in this engagement |
| Accept / Reject auto-fix | ✅ Fixed | Reject restores pre-fix snapshot |
| Audit changelog per page | ✅ Done | `_aa_autofix_history` post meta |
| Rollback / revision history | ⚠️ Partial | See revision note below |
| Dashboard widget | ⚠️ Partial | Exists but may read stale meta |
| WCAG level selector (A / AA) | ✅ Done | Setting wired to axe-core tags including wcag22aa |
| Background / non-blocking AI calls | ❌ Missing | All Claude calls are synchronous |
| Per-user usage limits | ❌ Missing | No rate limiting implemented |
| WordPress Coding Standards | ⚠️ Partial | Generally OK; some legacy cruft |
| Bricks Builder API integration | ✅ Done | Uses `BRICKS_DB_PAGE_CONTENT` meta |
| AutomaticCSS class references | ⚠️ Partial | Referenced in prompts; not validated |
| Secure API key storage | ✅ Fixed | Masking bug repaired; field renders empty, preserves existing key on save |
| Nonces + capability checks | ✅ Done | All REST routes check `edit_posts` |

### WCAG Version Note
The axe-core scanner supports WCAG 2.2 AA. Tags are selected dynamically based on the compliance level setting: AA includes `wcag2a`, `wcag2aa`, `wcag21aa`, `wcag22aa`. The compliance level from Settings is passed to the JS via `wp_localize_script` and used in axe `runOnly` config.

### Revision / Rollback Note
The spec says to "leverage WordPress native revisions." The current implementation saves a Bricks JSON snapshot into post meta (`bricks_revision_{context}_{timestamp}`) before each auto-fix. This is a custom snapshot approach, not native WP revisions. It works for undo purposes but does not appear in the standard WP revision history UI.

### 34-Component Requirement Coverage
The spec defines specific accessibility requirements for 34 Bricks element types (Heading, Button, Image, Form, Counter, Accordion, Tabs, Carousel, etc.), each mapped to specific WCAG success criteria.

The current implementation does **not** address these on a per-component basis. Axe-core detects violations generically, and Claude receives the raw Bricks element JSON plus the axe violation — it is not given the spec's per-component guidance. For Phase 2, a component-specific prompt library or system prompt section would significantly improve fix accuracy.

---

## Part 2 — Code Quality Review

### Overall Assessment

The codebase is functional and demonstrates reasonable WordPress development practices. The namespace (`Accessibility_Auditor`), REST API registration, permission callbacks, and sanitization are all done correctly. However, the plugin shows clear signs of iterative debugging under pressure — particularly `AI.php`, which has accumulated significant technical debt.

### Scoring System — `ScanManager.php`

**Quality: Good. One concern.**

- Formula correctly implements the spec: `100 - (issue_count × 5)`, min 0 ✅
- Score counts per-node instances (one missing alt per image = one issue each) ✅
- Grades in the JS component match the spec (A≥95, B≥85, C≥70, D≥50) ✅
- `calculate_score()` counts only `violations` — `incomplete` (needs-review) items do **not** reduce the score. ✅

### API Key Handling — `Settings.php`

**Fixed.** The field renders with `value=""` (empty) so no masked characters are ever submitted. On save, if the submitted value is empty the existing key is preserved. No data-destructive behaviour.

### Claude API Integration — `AI.php`

**Quality: Working. Cleaned up significantly during this engagement.**

**What works well:**
- API call structure is correct (Anthropic v1/messages endpoint)
- JSON mode system prompt is simple and effective
- `normalize_ai_response()` robustly handles markdown fences, control characters, trailing commas, bracket completion
- `apply_patch_to_bricks_element()` with `changes` / `added_keys` / `removed_keys` is a well-thought-out patch format
- Deep merge preserves Bricks structure
- `revert_fix()` restores pre-fix snapshot correctly

**Problems:**

1. **Synchronous HTTP with 300-second timeout.** `wp_remote_post()` blocks the PHP process for up to 5 minutes. If Claude is slow or the page has many elements, this will hit PHP `max_execution_time`, browser connection timeouts, or WP heartbeat conflicts. The spec specifically requires non-blocking async calls.

2. **No API error recovery.** If Claude returns a 529 (overloaded) or rate-limit error, a `WP_Error` is returned but the user sees no message. Retry logic or a user-facing error notice would improve reliability.

3. **Model ID hardcoded** in `ClaudeClient.php` (`const MODEL = 'claude-sonnet-4-20250514'`). Should be a configurable setting so it doesn't require a code change when the model is updated.

### `Pages_Column.php`
Clean and correct. Reads `_aa_scan_status`, `_aa_last_scan_id`, `_aa_scan_score`, `_aa_scan_summary` — consistent with what `ScanManager` writes. No issues.

### `Revisions.php`
Simple and correct. Logs to `_aa_autofix_history` array in post meta.

### `Loader.php`
The asset enqueue guard was recently fixed (current engagement). Now correctly handles both Bricks frontend preview and Bricks admin editor contexts, with a capability check. Clean.

---

## Part 3 — Auto-Fix Feasibility Assessment

### The Core Question

Can Claude reliably fix accessibility issues in Bricks Builder pages by modifying element JSON?

**Short answer: Yes, for a defined subset of issues. No, as a general-purpose "fix everything" button.**

### What Claude Can Fix Reliably

These issue types have a clear, deterministic fix that maps directly to a Bricks JSON field:

| Issue Type | Fixable? | How |
|---|---|---|
| Missing `alt` text on images | ✅ Yes | Set `settings.image.alt` |
| Empty `aria-label` on links/buttons | ✅ Yes | Set `settings.url.ariaLabel` + `settings.attributes['aria-label']` |
| Missing `title` on iframes | ✅ Yes | Set `settings.attributes['title']` |
| Wrong semantic heading tag | ✅ Yes | Set `settings.tag` (e.g., `h2` → `h3`) |
| Redundant `role` attributes | ✅ Yes | Remove via `removed_keys` |
| Missing form label associations | ⚠️ Partial | Works if it's an `aria-label`; harder if it requires a separate label element |
| `aria-live` on dynamic content | ✅ Yes | Set `settings.attributes['aria-live']` |

### What Claude Cannot Fix Reliably

| Issue Type | Why Not Fixable |
|---|---|
| Color contrast failures | Claude doesn't know the actual rendered color values. ACSS uses CSS variables — the Bricks JSON stores class names, not hex values. Claude can't compute contrast ratios. |
| Keyboard navigation / focus order | Requires understanding layout structure across multiple elements. No single-element JSON patch can fix tab order. |
| Heading hierarchy issues | Requires context of the entire page's heading structure, not just one element. Claude would need the full element tree. |
| Complex ARIA patterns (accordion, tabs) | These require coordinated attributes across parent/child elements. The current per-element approach handles one element at a time. |
| Decorative images (empty alt required) | Claude often adds descriptive alt text to images that should have `alt=""` (decorative). Without visual context, it can't determine intent. |
| Text spacing / resize issues | WCAG 2.2 SC 1.4.12 — requires CSS analysis, not element attribute changes. |

### What This Means for Phase 2

The approach the developers took is architecturally correct. The main thing needed is to **constrain what Claude is asked to fix** rather than passing all issues through the auto-fix flow.

**Recommended approach for Phase 2:**

1. **Issue type whitelist.** Only send issues with axe rule IDs that have known, safe JSON-level fixes (e.g., `image-alt`, `link-name`, `button-name`, `frame-title`, `aria-required-attr`). For anything else, fall back to guided fix only.

2. **Fix the synchronous blocking.** Use Action Scheduler (already has a settings checkbox for it) to run Claude calls in background jobs and push results back via transients or WebSockets.

4. **Per-component prompt context.** For Phase 2, each Bricks element type should have a spec-aligned system prompt section that tells Claude exactly what the accessibility requirement is (from the 34-component spec) rather than relying on general WCAG knowledge.

5. **Confirmation step before applying.** The spec requires user confirmation before saving AI changes. Currently, auto-fix applies immediately and the user can only reject after the fact. A preview/diff step would be better UX.

### Verdict

**Yes, proceed to Phase 2 — with scoped expectations.**

The technical foundation is working. The auto-fix mechanism (Claude → JSON patch → Bricks update → snapshot → revert) is sound. Claude handles the clear-cut cases (aria-labels, alt text, tag corrections) reliably when given a well-structured prompt with the element JSON. The unreliable cases (color contrast, layout, complex ARIA patterns) should be excluded from auto-fix scope and handled by guided-fix only.

A realistic Phase 2 scope would be: auto-fix covering 8–12 high-confidence axe rule IDs, plus guided fix for everything else, plus the blocking/async fix.

---

## Summary of Items to Address Before Phase 2

### Fixed in this engagement
- [x] API key masking bug in `Settings.php`
- [x] WCAG 2.2 (`wcag22aa`) tag added; compliance level wired to axe scanner
- [x] `generate_changelog()` updated to use correct patch format keys
- [x] Dead code and unused helper functions removed from `AI.php`

### Critical (must fix before Phase 2)
- [ ] Synchronous Claude calls — will time out on real pages

### High Priority
- [ ] Auto-fix issue type whitelist (only fixable rule IDs go through Claude)

### Medium Priority
- [ ] User confirmation step before auto-fix is applied (not just after)
- [ ] Per-component prompt guidance aligned with 34-component spec
- [ ] API error recovery (retry logic or user-facing error message)
- [ ] Native WP revision integration (if Gabriel wants it in revision history UI)
- [ ] Usage limit / rate limiting per spec requirement

### Low Priority
- [ ] Confirm with Gabriel that `incomplete` items correctly do not reduce score (current behaviour)
- [ ] Make Claude model ID configurable vs hardcoded
- [ ] Dashboard widget — verify meta key reads are consistent
