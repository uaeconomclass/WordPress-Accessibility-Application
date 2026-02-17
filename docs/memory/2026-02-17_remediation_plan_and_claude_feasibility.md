# Remediation Plan + Claude Auto-fix Feasibility (2026-02-17)

## Feasibility verdict
- Claude-based auto-fix is feasible for a bounded subset of issues.
- Recommended scope: alt text, labels, aria/title completion, and limited semantic normalization.
- Not realistic to guarantee safe full auto-fix for all WCAG scenarios without human review.

## Guardrail design (must-have)
1. Strict JSON schema for AI patch payloads + server-side validator.
2. Resource-level authorization (`current_user_can('edit_post', $post_id)`).
3. Dry-run diff preview before apply, explicit confirmation for persistence.
4. Atomic apply + rollback in native WP revisions.
5. Post-apply re-scan and rollback on regression/failure.

## Suggested phased implementation
- Phase 1: Stabilize endpoints and security/callback correctness.
- Phase 2: Fix settings/key handling and metadata consistency.
- Phase 3: Introduce validator + safe patch apply engine.
- Phase 4: Add revision UX + audit log + score delta visualization.
- Phase 5: Expand issue coverage gradually with allowlisted fix strategies.

## Client communication stance
- Current code demonstrates partial requirement coverage (scan, scoring, guided + partial auto-fix path).
- Refactoring and hardening are required before production confidence.
- If research exceeds 20 hours, provide explicit checkpoint with completed hardening status and residual risks.
