# Security Audit: plugin_thold

Audit date: 2026-03-09
Auditor: static analysis (grep + manual code review)
Cacti environment: not available; findings based on source only.

---

## Findings

### FIND-001: [SQL] Column name interpolation in setup.php

- **Severity**: Medium
- **Confidence**: High
- **File**: setup.php
- **Line**: 345, 350, 386, 391
- **Evidence**:
  ```
  345: db_fetch_cell_prepared('SELECT thold_' . $matches[1] . ' FROM thold_data WHERE data_template_rrd_id = ?', [$data_template_rrd_id])
  350: db_fetch_cell_prepared('SELECT time_' . $matches[1] . ' FROM thold_data WHERE data_template_rrd_id = ?', [$data_template_rrd_id])
  386: db_fetch_cell_prepared('SELECT thold_' . $matches[1] . ' FROM thold_data WHERE data_template_rrd_id = ?', [$data_template_rrd_id])
  391: db_fetch_cell_prepared('SELECT time_' . $matches[1] . ' FROM thold_data WHERE data_template_rrd_id = ?', [$data_template_rrd_id])
  ```
- **Description**: `$matches[1]` is interpolated directly into the column name portion of the query. The value comes from two regex captures: `/\|thold\:(hi|low)\:/` (captures `hi` or `low`) and `/\|thold\:(warning_hi|warning_low)\:/` (captures `warning_hi` or `warning_low`). PDO prepared statements cannot parameterise column names, so if the regex capture were ever widened or reused elsewhere with a different pattern, SQL injection becomes possible.
- **Exploitability**: Low in current form — the regex strictly constrains captures to a four-value set. Risk is that the pattern is copy-pasted or the regex is relaxed without updating the column-name guard.
- **Remediation**: Replace interpolation with an explicit allowlist check:
  ```php
  $allowed_thold = ['hi', 'low', 'warning_hi', 'warning_low'];
  if (!in_array($matches[1], $allowed_thold, true)) {
      cacti_log('WARNING: unexpected thold column suffix: ' . $matches[1], false, 'THOLD');
      break;
  }
  $value = db_fetch_cell_prepared('SELECT thold_' . $matches[1] . ' FROM thold_data WHERE data_template_rrd_id = ?', [$data_template_rrd_id]);
  ```
- **TDD status**: Ready (tests in tests/Security/XssOutputTest.php, allowlist describe block)

---

### FIND-002: [SQL] Unparameterised DELETE in notify_lists.php

- **Severity**: Low
- **Confidence**: High
- **File**: notify_lists.php
- **Line**: 484, 488
- **Evidence**:
  ```
  484: db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id=' . $selected_items[$i]);
  488: db_execute('UPDATE thold_data SET notify_alert=' . get_request_var('id') . ' WHERE id=' . $selected_items[$i]);
  ```
- **Description**: Integer values are concatenated rather than bound. `$selected_items` comes from `sanitize_unserialize_selected_items()` which validates each element with `input_validate_input_number()`, so the current exploitability is low. The pattern is inconsistent with the rest of the codebase which uses prepared statements.
- **Exploitability**: Low — requires bypass of `sanitize_unserialize_selected_items()` integer validation.
- **Remediation**:
  ```php
  db_execute_prepared('DELETE FROM plugin_thold_threshold_contact WHERE thold_id = ?', [(int) $selected_items[$i]]);
  db_execute_prepared('UPDATE thold_data SET notify_alert = ? WHERE id = ?', [(int) get_filter_request_var('id'), (int) $selected_items[$i]]);
  ```
- **TDD status**: Ready

---

### FIND-003: [Auth] thold_webapi.php — review endpoint auth coverage

- **Severity**: Medium
- **Confidence**: Medium
- **File**: thold_webapi.php
- **Line**: 1 (file entry), all action handlers
- **Evidence**: File is included via `thold.php:39: include_once(...thold_webapi.php)`. Auth is inherited from `thold.php` which includes `auth.php`. Direct HTTP access to thold_webapi.php is not blocked since it lacks `cli_check.php` or a standalone auth header.
- **Description**: If thold_webapi.php can be accessed directly (not via thold.php), its action handlers run without the auth check applied by thold.php's include chain.
- **Exploitability**: Medium — requires direct URL access to thold_webapi.php. Cacti's web server configuration may mitigate this if the plugins directory requires auth at the vhost level.
- **Remediation**: Add at the top of thold_webapi.php (or confirm it is never directly web-accessible):
  ```php
  if (!defined('IN_CACTI_CONTEXT')) {
      die('<br><strong>This file is not meant to be accessed directly.</strong>');
  }
  ```
- **TDD status**: Seam required first

---

### FIND-004: [CSRF] State-changing POST handlers lack explicit token check

- **Severity**: Medium
- **Confidence**: Medium
- **File**: notify_lists.php, thold.php, thold_templates.php
- **Line**: notify_lists.php:537, thold.php:386, thold_templates.php:241
- **Evidence**:
  ```
  notify_lists.php:537: foreach ($_POST as $var => $val) { if (preg_match('/^chk_([0-9]+)$/', ...
  thold.php:386: foreach ($_POST as $var => $val) {
  ```
- **Description**: Mass-action POST handlers (bulk delete, bulk associate) do not call an explicit CSRF token verification function. Cacti core provides `form_verify_token()` / `check_request_token()` — these plugins do not call it before processing state-changing actions.
- **Exploitability**: Medium — requires a logged-in admin to visit an attacker-controlled page. Cacti's SameSite cookie settings may mitigate in modern browsers.
- **Remediation**: Add token check at the top of each action handler:
  ```php
  if (!form_verify_token()) {
      header('HTTP/1.1 400 Bad Request');
      exit;
  }
  ```
- **TDD status**: Seam required first (need to stub `form_verify_token`)

---

## Unknowns

- Cannot confirm whether Cacti's auth layer enforces session checks before any plugin file is served — no live Cacti environment available.
- `thold_daemon.php` uses `exec_background()` which calls PHP's `exec`; could not confirm whether any user-controlled data reaches `$process` arguments without full call-graph tracing.

## Blind Spots

- No dynamic analysis performed. Race conditions in threshold evaluation not assessed.
- Email/notification template injection (thold_functions.php notification rendering) not fully traced.
- No review of JavaScript files in themes/ for DOM-based XSS.

## Assumptions

- Cacti's `sanitize_unserialize_selected_items()` correctly validates all elements as integers.
- `auth.php` include at thold.php top correctly redirects unauthenticated requests.
- `db_qstr()` used in notify_queue.php filter queries provides adequate escaping.

## Seams Needed

1. Extract column-name resolution (FIND-001) to `src/TholdColumnResolver.php`.
2. Extract bulk-action DB calls (FIND-002) to `src/NotificationListRepository.php`.
3. Extract webapi action dispatch (FIND-003) to `src/WebApiDispatcher.php` with auth gate.
4. Stub `form_verify_token` in CactiStubs.php to enable CSRF tests (FIND-004).
