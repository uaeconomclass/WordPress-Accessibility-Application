# AI Auto-Fix Architecture Refactor Plan

## Goal

Stabilize and scale the Accessibility Auditor auto-fix workflow without rewriting the plugin.

Keep the current core concept:
- scan rendered Bricks preview DOM with axe-core
- map issues to Bricks elements
- apply patches to Bricks JSON
- re-scan and verify

## What Is Conceptually Correct (Keep)

Current pipeline concept is sound:

1. `axe` scans the **rendered Bricks preview DOM**
2. Plugin stores normalized scan results + page status
3. User selects issue in Bricks UI
4. AI (Claude) generates a minimal patch
5. Plugin applies patch to **Bricks JSON**
6. Plugin saves snapshot / supports rollback
7. Plugin re-scans to verify result

This is the right architecture for a Bricks-centered accessibility workflow.

## Current Architectural Problem

`classes/AI.php` has grown into a **god object** that mixes:
- REST controllers
- Anthropic API HTTP calls
- prompt building
- AI response cleanup / JSON normalization
- patch application / deep merge logic
- Bricks element finding and mutation
- revision/snapshot orchestration
- logging/debug flow

This makes the auto-fix pipeline difficult to maintain, test, and safely expand.

## Refactor Direction (Pragmatic, Incremental)

Refactor toward a **guarded AI patch pipeline** with separated responsibilities.

### Proposed Modules

- `AI_Controller`
  - Register REST routes
  - Permission checks
  - Request parsing / response formatting

- `AutoFixService`
  - Orchestrates end-to-end auto-fix flow

- `GuidedFixService`
  - Handles guided-fix generation flow

- `ClaudeClient`
  - Encapsulates Anthropic API calls
  - Error mapping / retries / timeout policy

- `Prompt Builders`
  - `AutoFixPromptBuilder`
  - `GuidedFixPromptBuilder`
  - Versioned prompt templates

- `AiResponseNormalizer`
  - Fence stripping, cleanup, JSON extraction/normalization

- `BricksPatchValidator`
  - Safety guardrails (whitelist/denylist)
  - Patch shape validation
  - `element_id` target validation

- `BricksPatchApplier`
  - `changes` / `added_keys` / `removed_keys`
  - Deep merge / deep remove

- `BricksElementFinder` / `BricksElementExtractor`
  - Issue -> target element(s) mapping
  - Bricks JSON traversal helpers

- `RevisionService`
  - Snapshot save / revert / changelog support

- `IssueRouter` + `Strategy/*`
  - Route `axe rule id` to the appropriate fix strategy

## Phase 2 Priorities (Highest ROI)

### 1) Add Safety Rails Before Expanding Auto-Fix

Before adding more AI behavior, add strict validation:

- **Issue whitelist**
  - Only supported/fixable `axe` rule IDs enter auto-fix
  - Everything else falls back to guided-fix only

- **Patch whitelist**
  - Only allow known-safe Bricks paths in Phase 2
  - Example safe candidates:
    - `settings.altText`
    - `settings.attributes.*`
    - `settings.url.ariaLabel`
    - `settings.tag`
    - `settings._cssCustom` (guarded)

- **Patch sanity checks**
  - Max depth / size
  - No multi-element writes in a single-element flow
  - Patch `element_id` must match target

This is the most important reliability improvement for LLM-driven fixes.

### 2) Split Prompt Logic From Orchestration

Move prompt text out of `AI.php`:
- improve maintainability
- support prompt versioning
- make issue-type specific prompt tuning easier
- avoid contradictory instructions

Log `prompt_version` and `strategy_key` so behavior can be audited over time.

### 3) Make Pipeline Stages Explicit

Treat the flow as separate stages:

1. Generate patch
2. Normalize response
3. Validate patch
4. Apply patch
5. Save snapshot + changelog
6. Re-scan / verify

Each stage should:
- log structured context (`post_id`, `rule_id`, `element_id`, status)
- return actionable errors (not silent failures)

### 4) Add Strategy-Based Routing (Rule-Specific Fix Logic)

Do not run a single generic auto-fix prompt for every issue type.

Introduce an `IssueRouter` / `Strategy` layer:
- `ImageAltFixStrategy`
- `LinkNameFixStrategy`
- `ButtonNameFixStrategy`
- `FrameTitleFixStrategy`
- `HeadingTagFixStrategy`
- `ColorContrastFixStrategy` (guarded)
- `GuidedOnlyStrategy` (default fallback)

Benefits:
- safer rollout
- better prompts
- easier debugging
- clearer client expectations

### 5) Async Execution (Near-Term Requirement)

Synchronous Claude calls in REST requests are not scalable for real pages/UI usage.

Recommended:
- Action Scheduler jobs for auto-fix tasks
- UI polls job status (`queued`, `running`, `done`, `failed`)
- retry/backoff for transient API failures

This improves reliability and Bricks editor UX.

## Suggested Target Structure

```text
classes/
  AI/
    AI_Controller.php
    AutoFixService.php
    GuidedFixService.php
    ClaudeClient.php
    IssueRouter.php
    Prompt/
    Response/
    Patch/
    Bricks/
    Revision/
    Strategy/
    Support/
    Async/
```

## Incremental Migration Plan (Minimize Breakage)

1. Extract `ClaudeClient`
2. Extract `AiResponseNormalizer`
3. Extract `BricksPatchApplier`
4. Add `BricksPatchValidator` (initial whitelist)
5. Introduce `AutoFixService`
6. Convert `AI.php` into a thin controller layer
7. Add `IssueRouter` + a small set of strategies
8. Add async job execution

This sequence preserves working behavior while reducing risk.

## Phase 2 Scope Recommendation

Auto-fix should initially support only a **constrained subset** of high-confidence `axe` rule IDs (e.g. 8-12), while guided-fix remains available for all others.

This gives a realistic, reliable Phase 2 without overpromising “fix everything.”

## Architectural Success Criteria

Refactor is successful when:
- invalid Claude output cannot corrupt Bricks JSON
- new supported rule IDs can be added without editing a monolithic file
- debugging a failed auto-fix does not require tracing all of `AI.php`
- Bricks UI is not blocked by long AI requests
- rollback remains reliable
