# Backlog: plugin_thold

Issues derived from SECURITY-AUDIT.md findings and tooling setup.

---

### Issue #1: hardening(sql): add allowlist guard for column name interpolation in setup.php

- **Priority**: high
- **Labels**: [security, hardening, priority: high]
- **Branch**: hardening/1-thold-column-name-allowlist
- **Evidence**: setup.php:345,350,386,391
- **Acceptance criteria**:
  - [ ] A constant or function defines the exact set of allowed column suffixes: `['hi', 'low', 'warning_hi', 'warning_low']`
  - [ ] Each call site checks `in_array($matches[1], $allowed, true)` before interpolation
  - [ ] An unexpected value logs a warning via `cacti_log()` and skips the replacement
  - [ ] Existing behaviour is unchanged for valid suffixes
  - [ ] Unit test in `tests/Security/XssOutputTest.php` passes (allowlist describe block)
- **Dependencies**: none

---

### Issue #2: hardening(sql): replace concatenated DELETE/UPDATE with prepared statements in notify_lists.php

- **Priority**: high
- **Labels**: [security, hardening, priority: high, tech-debt]
- **Branch**: hardening/2-notify-lists-prepared-statements
- **Evidence**: notify_lists.php:484,488
- **Acceptance criteria**:
  - [ ] `db_execute('DELETE ... thold_id=' . $selected_items[$i])` replaced with `db_execute_prepared(..., [(int) $selected_items[$i]])`
  - [ ] `db_execute('UPDATE thold_data SET notify_alert=' . ...)` replaced with prepared equivalent
  - [ ] All other concatenated queries in notify_lists.php audited and converted
  - [ ] Existing test suite passes
- **Dependencies**: none

---

### Issue #3: hardening(auth): prevent direct HTTP access to thold_webapi.php

- **Priority**: high
- **Labels**: [security, hardening, priority: high]
- **Branch**: hardening/3-thold-webapi-context-guard
- **Evidence**: thold_webapi.php:1 (missing context guard)
- **Acceptance criteria**:
  - [ ] A `defined('IN_CACTI_CONTEXT') or die(...)` guard is added at the top of thold_webapi.php
  - [ ] `thold.php` defines `IN_CACTI_CONTEXT` before including thold_webapi.php
  - [ ] Direct HTTP GET to thold_webapi.php returns a non-200 response or no output
  - [ ] Existing functionality via thold.php is unaffected
- **Dependencies**: none

---

### Issue #4: hardening(csrf): add form token verification to state-changing POST handlers

- **Priority**: medium
- **Labels**: [security, hardening, priority: medium]
- **Branch**: hardening/4-thold-csrf-tokens
- **Evidence**: notify_lists.php:537, thold.php:386, thold_templates.php:241
- **Acceptance criteria**:
  - [ ] `form_verify_token()` (or Cacti equivalent) called before each bulk-action handler
  - [ ] Invalid token results in HTTP 400 and no state change
  - [ ] CSRF test in `tests/Security/XssOutputTest.php` passes (after seam is created)
- **Dependencies**: Issue #5 (seam)

---

### Issue #5: tech-debt(seam): extract bulk-action DB calls to src/ for testability

- **Priority**: medium
- **Labels**: [tech-debt, testability]
- **Branch**: tech-debt/5-thold-src-seams
- **Evidence**: notify_lists.php:484, setup.php:345, thold_webapi.php action handlers
- **Acceptance criteria**:
  - [ ] `src/TholdColumnResolver.php` provides `resolve(string $suffix): string` with allowlist
  - [ ] `src/NotificationListRepository.php` provides typed methods for delete/update operations
  - [ ] `src/WebApiDispatcher.php` provides auth-gated action dispatch
  - [ ] All existing behaviour preserved
  - [ ] PHPStan level 6 passes on `src/`
- **Dependencies**: none

---

### Issue #6: tooling(ci): configure Composer, Pest 4, PHPStan, and Infection for plugin_thold

- **Priority**: medium
- **Labels**: [tooling, ci]
- **Branch**: tooling/6-thold-composer-ci
- **Evidence**: composer.json, phpstan.neon, infection.json, pest.php created in this audit
- **Acceptance criteria**:
  - [ ] `composer install` succeeds
  - [ ] `vendor/bin/pest` runs and reports test results
  - [ ] `vendor/bin/phpstan analyse` runs at level 6 on `src/` and `tests/`
  - [ ] CI workflow runs on push and pull_request
  - [ ] Coverage threshold 80% enforced in CI
- **Dependencies**: Issue #5 (src/ must have testable code)
