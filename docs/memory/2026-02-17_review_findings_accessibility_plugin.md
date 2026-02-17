# Review Findings (2026-02-17)

## Critical
1. REST route `/aa/v1/save-fix` points to missing callback method.
- Route registered, but `save_fix` method is commented out.
- Risk: runtime callback error / broken endpoint contract.

## High
2. Claude API key settings field can overwrite real key with masked placeholder characters.
- UI writes masked bullets into password value and sanitize persists submitted value.
- Risk: accidental credential loss.

3. AI auto-fix authorization is too broad and post resolution is weak.
- Uses `edit_posts` and may infer `post_id` from HTTP referrer.
- Risk: weak authorization boundary and wrong post mutation.

4. Rollback implementation does not use native WP revisions as required.
- Current approach stores snapshots in post meta.
- Risk: requirement mismatch and weaker operational rollback UX.

## Medium
5. Dashboard uses inconsistent meta keys (`_aa_*`) while scan manager writes `_acss_*`.
- Risk: dashboard metrics/status drift or zeroed values.

6. Duplicate `Admin::init()` invocation pattern can cause duplicated hooks/widget behavior.

7. WCAG 2.2 targeting is incomplete in scanner options.
- JS runOnly tags currently target wcag2a/wcag2aa/wcag21aa.

8. Auto-fix depends on Bricks constant/data path without robust guard when Bricks unavailable.

## Overall quality signal
- Architecture is salvageable; scanning + reporting foundations are present.
- Main risk concentration is in `classes/AI.php` complexity and operational hardening gaps.
