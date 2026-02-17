# Code Style & Conventions

- Language: PHP (WordPress plugin style), plus browser JS for UI.
- Patterns observed:
  - Static utility-like classes for WP hooks (`Loader`, `ScanManager`, `Report`, `Settings`).
  - Namespace usage in most files: `Accessibility_Auditor`.
  - WP APIs: hooks (`add_action`, `add_filter`), post meta, REST routes, AJAX handlers.
- Security conventions present but inconsistent:
  - Some nonce checks and capability checks are implemented.
  - Some flows rely on broad capabilities and inferred `post_id` from referrer.
- Data conventions:
  - Scan summary stored in post meta keys with `_acss_*` naming.
  - Findings stored in custom table `${wpdb->prefix}acss_scans`.
- Technical debt indicators:
  - `classes/AI.php` is large with many commented legacy blocks and mixed approaches.
  - Inconsistent key naming in admin/dashboard modules (`_aa_*` vs `_acss_*`).
