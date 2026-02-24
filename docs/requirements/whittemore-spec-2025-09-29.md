# Whittemore Accessibility Plugin Requirements (Normalized Snapshot)

Source: client-provided text paste (Google Doc "Accessibility Plugin Requirements for Bricks & AutomaticCSS")  
Document owner: Gabriel Whittemore  
Last updated in source: 2025-09-29

Purpose of this file: maintain a local, versioned copy of the active requirements so engineering and testing can work without depending on Google Docs access.

## Product Goal

Build a custom WordPress plugin for Bricks Builder sites using Automatic.css that provides:

- automated WCAG-aligned scanning
- page-level scoring and indicators
- AI-guided remediation
- AI-assisted automated fixes (via Claude)
- rollback/auditability
- dashboard reporting

## Core Functional Requirements

### 1. Accessibility Scanning

- Scan pages for accessibility barriers using a reliable engine (e.g. `axe-core`, Lighthouse, or equivalent WCAG-aligned API/mappings).
- Findings must map to WCAG 2.x criteria and distinguish:
  - automatically detectable issues
  - issues requiring human review
- Surface actionable findings (alt text, contrast, ARIA, labels, etc.).
- Requirements language references WCAG 2.2 and A/AA/AAA support.

### 2. Page List Indicators (WP Admin Pages Screen)

- Add custom status column via WordPress hooks:
  - `manage_pages_columns`
  - `manage_pages_custom_column`
- Each page row shows a colored indicator:
  - Green: passes selected criteria
  - Yellow: minor issues
  - Red: major violations
- Indicator hover/click should link to detailed report.
- Requires per-page scan result storage and refresh strategy.

### 3. AI-Powered Assistance (Claude)

#### Guided Fixes

- One-click "Generate Fix Guide"
- Prompt Claude with page issues + Bricks + Automatic.css context
- Return step-by-step instructions using Bricks terminology and ACSS utilities
- Cite WCAG/W3C techniques for traceability where appropriate

#### Automated Fixes

- One-click "Auto-Fix"
- Claude returns structured JSON describing Bricks element setting changes / ACSS adjustments only
- Plugin applies changes via Bricks APIs / Bricks-safe data model updates
- Never edit raw theme code
- All changes saved as a revision/snapshot for rollback
- Changelog entry must record what changed (element, setting, before/after)

### 4. Rollback & Revision History

- Rollback required for every auto-fix action
- UI should expose revision history / restore path
- Built-in changelog should track:
  - page
  - date/time
  - fix type
  - user action

### 5. Dashboard Reporting

Add a WordPress dashboard widget (`wp_add_dashboard_widget`) showing:

- Overall site score (letter grade and/or percentage)
- Issue summary grouped by severity
- Recommended actions / next steps
- Links to reports and/or AI actions

Widget should update after scans/fixes and use clear color coding.

## Technical Requirements

### WordPress Integration

- WordPress 6.0+ compatible
- Use Plugin API / WP coding patterns
- Use admin hooks for pages and dashboard integration
- Use Settings API (or equivalent) for plugin settings
- Sanitize settings and external input
- Use batch/AJAX/Cron patterns to avoid timeouts

### Bricks Builder + Automatic.css Integration

- Fixes must target Bricks builder data/model/API (not raw theme files)
- Automatic.css utilities/classes may be used for fixes
- Any ACSS usage should align with ACSS docs/patterns

### WCAG Compliance Levels

- User-selectable compliance target (e.g. WCAG 2.1 AA vs 2.2 AA)
- Scanning/recommendations should align with selected level
- Deliverable must include WCAG mapping matrix

### Accessibility APIs / Mappings

- Use WCAG-aligned scanning and Core-AAM-informed checks for ARIA semantics
- Combine automated checks with manual-review guidance where needed

### Claude API Integration

- Store API key securely (server-side)
- Enforce usage limits per customer
- Non-blocking/async requests preferred (queue/background)
- Restrict AI actions to authorized users
- Graceful error handling
- Require confirmation before saving AI changes

### Performance & Security

- Minimal asset loading
- Avoid heavy scans on every page load
- Prefer on-demand / publish-update / AJAX scanning
- Use nonces + capability checks
- Sanitize AI inputs/outputs before applying
- Follow WP plugin security best practices

## Requirement Clarifications (Scoring)

### Page Score Formula

- Start from `100`
- Subtract exactly `5` points per issue instance
- Severity does **not** change point deduction
- Multiple instances count independently
- Minimum score is `0`

### Letter Grade Thresholds

- `A` = `>= 95`
- `B` = `>= 85`
- `C` = `>= 70`
- `D` = `>= 50`
- Below 50 still floors at score `0`; requirement text describes minimum letter as `D` (needs confirmation for UI behavior below 50)

## Requirement Clarifications (Bricks Components)

The requirements define element-level accessibility expectations for Bricks components (examples):

- Heading
- Basic Text / Rich Text
- Button / Icon / Image / Video
- Accordion / Tabs / Form
- Map / Alert / Countdown / Counter
- Pricing Tables / Progress Bar / Pie Chart
- Team Members / Testimonials / Logo
- Gallery / Audio / Carousel / Slider / SVG

Each entry maps to WCAG success criteria and should inform:

- scanner interpretation / reporting
- guided remediation prompts
- auto-fix strategy eligibility (safe vs guided-only)

## Engineering Guidance Derived From This Spec

- Treat "auto-fix everything" as a phased objective, not day-1 guarantee.
- Maintain a rule-level and component-level coverage matrix.
- Separate:
  - `detect`
  - `guided fix`
  - `auto-fix`
- Require validation + rollback + re-scan for all auto-fix paths.

