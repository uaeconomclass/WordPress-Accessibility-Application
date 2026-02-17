# Task Completion Checklist

For meaningful plugin changes, run this sequence before handoff:

1. Syntax checks
- Run `php -l` on all touched PHP files.

2. Security checks (minimum)
- Verify nonce validation on write endpoints.
- Verify capability checks include resource-level permission (`edit_post` when modifying post data).
- Ensure no secret/API key is leaked to client-side scripts.

3. Functional smoke checks in wp-env
- Open Bricks editing flow and run accessibility scan.
- Confirm scan persistence updates expected meta keys.
- Test guided-fix endpoint response path.
- Test auto-fix path only with safe sample issue + verify Bricks content remains valid JSON.

4. Data integrity checks
- Ensure revisions/rollback mechanism is actually callable from UI and persists expected audit trail.
- Re-scan after auto-fix and compare score/issue deltas.

5. Git hygiene
- Commit only relevant files.
- Keep `development` branch updated and push to `origin`.
