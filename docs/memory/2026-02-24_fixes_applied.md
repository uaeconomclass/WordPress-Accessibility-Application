# Fixes Applied — 2026-02-24

All changes made during the debug/fix session. Files: `classes/AI.php`, `classes/ScanManager.php`,
`classes/Loader.php`, `classes/Pages_Column.php`, `assets/js/editor-wc.js`.

---

## 1. Meta key prefix mismatch (_acss_ → _aa_)

**Problem:** `ScanManager::save_scan()` wrote `_acss_last_scan_id`, `_acss_scan_score`,
`_acss_scan_status`, `_acss_scan_summary`. Every reader (Admin.php, Pages_Column.php, Loader.php,
Report.php) expected `_aa_*`. Result: dashboard zeros, no column badges.

**Fix:** Renamed all four keys in `ScanManager.php` lines 59-62 to `_aa_*` to match readers.

**Files:** `classes/ScanManager.php`

---

## 2. Score formula inconsistency

**Problem:** JS calculated `100 - violations.length * 5` (per-rule, violations only).
PHP `calculate_score()` counted per-node and included incomplete — produced wildly different numbers.

**Fix:** Rewrote `calculate_score()` to match JS: `max(0, 100 - count($results['violations']) * 5)`.
Added comment: "Match JS formula: 5 points per violation rule, not per node."

**Files:** `classes/ScanManager.php:154-160`

---

## 3. PHP Fatal — WP_Error namespace

**Problem:** `classes/AI.php` used `new WP_Error(...)` without `use WP_Error;`. Inside
`namespace Accessibility_Auditor;` PHP looked for `Accessibility_Auditor\WP_Error` → fatal.

**Fix:** Added `use WP_Error;` at top of AI.php (line 4).

**Files:** `classes/AI.php:4`

---

## 4. PHP 8.3 TypeError on null array access

**Problem:** `$issue['nodes'][0]['html']` — `$issue['nodes']` can be null/empty.
PHP 8.3 throws `TypeError: Cannot access offset on null` (not caught by `??`).

**Fix:** Changed to `($issue['nodes'][0] ?? [])['html'] ?? '(no html field)'`.

**Files:** `classes/AI.php` — inside `extract_bricks_elements_from_issue()`

---

## 5. sprintf crash on %root%

**Problem:** Auto-fix prompt contained `%root%` as example CSS. `sprintf()` treats `%r` as unknown
format specifier → `ValueError: Unknown format specifier "r"`.

**Fix:** Changed approach entirely — prompt now tells Claude to use `#brxe-{element_id}` directly
instead of `%root%`. The literal `%root%` is never passed to `sprintf()` anymore.

**Context:** Bricks does NOT replace `%root%` when CSS is set via `update_post_meta` directly
(only works through Bricks internal save). `#brxe-{id}` is the correct literal to use.

**Files:** `classes/AI.php` — `apply_auto_fix()` prompt

---

## 6. axe style injection breaks HTML matching

**Problem:** axe-core injects `style="outline: rgba(90, 200, 250, 0.9) dashed 2px;"` into
`node.html` during scanning (visual highlight). When `extract_bricks_elements_from_issue()`
tried to match `node.html` content against Bricks settings text, the style attribute made
the match fail.

**Fix:** Strip injected style attr before matching:
```php
$clean_html = preg_replace('/\s+style="[^"]*"/', '', $html);
```

**Files:** `classes/AI.php` — `extract_bricks_elements_from_issue()` fallback logic

---

## 7. Element ID extraction — 3-level fallback

**Problem:** Some axe issues have bare tag selectors (`["a"]`, `["pre"]`) with no `#brxe-` ID
in the target array. The original regex only looked for `#brxe-{id}` in target selectors.

**Fix:** Added 3-level fallback chain:
1. `#brxe-{id}` in `node.target` selectors (original path)
2. `id="brxe-{id}"` attribute in `node.html` (handles `<pre id="brxe-abc">` etc.)
3. Content match: strip axe style attr, match `node.html` snippet against Bricks `settings.text`
   (handles inline elements like `<a>` inside text-basic blocks)

**Files:** `classes/AI.php` — `extract_bricks_elements_from_issue()`

---

## 8. Canvas reload after AI fix

**Problem:** After auto-fix, page content changes but Bricks editor shows stale canvas.
Attempts tried and failed:
- `iframe.src = url` — strips `?bricks=run` param → blank canvas
- `iframe.contentWindow.location.reload()` — also breaks Bricks Vue context → blank canvas
- `postMessage` to iframe — no effect (Bricks doesn't handle it)

**Fix:** `window.location.reload()` — full builder page reload after 1.5s delay.
Message shown: "✅ Fix applied! Reloading editor…"

**Files:** `assets/js/editor-wc.js` — after `_autoFix()` success path

---

## 9. Score badge not updating (Shadow DOM)

**Problem:** `updateAccessibilityUI()` called `document.querySelector('.aa-score-badge')` —
the badge is inside the Web Component's shadow DOM, so `document.querySelector` can't find it.

**Fix:** Changed to `this.shadowRoot.querySelector('.aa-score-badge')`.
Also added `this.score = score` to keep instance state consistent.

**Files:** `assets/js/editor-wc.js` — `updateAccessibilityUI()`

---

## 10. JS cache — version bump

**Problem:** After fixing editor-wc.js, browser served cached v0.1.0 → changes not visible.

**Fix:** Bumped version string from `'0.1.0'` to `'0.2.0'` in `Loader::enqueue_assets()`.

**Files:** `classes/Loader.php` — `wp_enqueue_script` version param

---

## 11. Pages column — meta key + enriched display

**Problem:** `Pages_Column::render_column()` read `_acss_scan_status` and `_acss_last_scan_id`
(wrong prefix). Column showed only a colored dot with no useful data.

**Fix:**
- Renamed meta keys to `_aa_*`
- Column now shows: score % (or status label if no score) + summary text alongside dot

**Files:** `classes/Pages_Column.php`

---

## 12. Settings API key masking (was already fixed)

**Problem (from 2026-02-17 review):** Password field showed masked bullets; if user clicked Save
without touching the field, the bullets would be submitted and overwrite the real key.

**Status:** `Settings::sanitizeOptions()` already handles this correctly:
```php
$out['claude_api_key'] = $submitted !== '' ? $submitted : ($prev['claude_api_key'] ?? '');
```
Field renders with `value=""` and placeholder text. Empty submit = keep existing. ✅

---

## 13. save_fix endpoint (was already implemented)

**Problem (from 2026-02-17 review):** Route registered but callback "commented out".

**Status:** `AI::save_fix()` exists at line 368 and is fully implemented. Logs acceptance to
`_aa_autofix_history` post meta via `Revisions::log_autofix()`. ✅

---

## What is still open (not fixed yet)

See [plan.md](plan.md) for full prioritized list. Short summary:

| Item | Priority | Notes |
|---|---|---|
| No Bricks guard in auto-fix | P1 | `BRICKS_DB_PAGE_CONTENT` undefined → fatal |
| Auth uses `edit_posts` not `edit_post($id)` | P1 | Per-resource check missing |
| `Admin::init()` called twice (line 66) | P1 | Duplicate dashboard widget |
| No post-apply re-scan trigger | P2 | Score stays stale until manual rescan |
| Letter grade (A-F) not shown in UI | P2 | Formula exists, no label rendered |
| Scan history per page | P2 | Table exists, no UI |
| Bulk scan | P3 | Action Scheduler not wired up |
| Rollback uses post meta, not WP revisions | P3 | Intentional for Bricks (WP revisions don't cover custom meta) |
