# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`thold`, "Thresholds", version 1.8.2) targeting Cacti 1.2.25+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: 8.1+ (CI matrix tests 8.1-8.4)
- **Platform**: Cacti Plugin Architecture (Cacti 1.2.25+)
- **Database**: MySQL/MariaDB with InnoDB engine
- **Alerting**: Email, Syslog, and SNMP Traps/Informs notification channels

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`)
- `CACTI-THOLD-MIB` for SNMP trap/inform definitions
- Optional: `gettext` for internationalization

## Project Structure

```
thold/                       # Repository root (install to plugins/thold/ in Cacti)
├── extras/                    # Supplementary assets
├── includes/                    # polling.php (poller hooks), settings.php (config UI), tab.php
├── service/                        # systemd unit for thold_daemon
├── tests/                             # Test suite (phpunit.xml)
├── themes/                              # CSS theme overlays
├── cli_import.php / cli_thresholds.php    # CLI threshold management utilities
├── notify_lists.php / notify_queue.php      # Notification list/queue administration
├── thold.php                                  # Main threshold administration UI
├── thold_daemon.php                             # Standalone high-scale daemon (bypasses poller hook)
├── thold_functions.php                            # Core utility/business logic
├── thold_graph.php / thold_notify.php               # Graph-threshold view / notification dispatch
├── thold_process.php / thold_templates.php            # Background processing / threshold templates
├── thold_webapi.php                                     # Web API endpoints
├── poller_thold.php                                       # Background poller entry point (CLI)
├── INFO                                                     # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                                                  # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
- **All functions and global variables** MUST be prefixed `thold_`: `thold_functions.php`, `thold_poller_output()`, `thold_config_settings()`.
- Match the existing prefix used by the function you are editing; do not introduce a new naming scheme.

### Database Tables
All plugin tables are prefixed `plugin_thold_`.

### Variables and Constants
- Access Cacti configuration via the global `$config` array; use `$config['base_path']` for absolute file paths.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
**ALWAYS use prepared statements** for database operations:

```php
// CORRECT
db_execute_prepared($sql, $params);
db_fetch_assoc($sql); // only for queries with no variable input
db_fetch_cell($sql);  // only for queries with no variable input

// WRONG - never concatenate request input into SQL
db_fetch_row("SELECT * FROM plugin_thold_thresholds WHERE id = $id");
```

### Input Validation and Sanitization
Sanitize inputs using `sanitize_thold_sort_string()` or Cacti's built-in input validation functions (`get_filter_request_var()`, `get_nfilter_request_var()`); never read `$_GET`/`$_POST` directly.

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

## Database Operations

Use Cacti's global database functions: `db_execute_prepared($sql, $params)` for writes, `db_fetch_assoc($sql)` / `db_fetch_cell($sql)` for reads.

## Internationalization

Use `__('String', 'thold')` for all user-facing strings to support internationalization.

## Plugin Architecture

### Data Flow
1. **Data Collection**: Cacti poller collects data.
2. **Interception**: `thold_poller_output()` (in `includes/polling.php`) receives the data.
3. **Processing**: Standard mode processes immediately within the poller hook; Daemon mode queues data for `thold_daemon.php` to process asynchronously.
4. **Alerting**: If a threshold is breached, `thold_functions.php` handles notification dispatch.

### Plugin Hooks
Register hooks in `setup.php` (see the full list of ~30 hooks covering device/graph/data-source actions, poller integration, and template change events); keep new hooks registered the same way via `api_plugin_register_hook($plugin, 'hook_name', 'callback', 'file.php')`.

### Daemon Mode
`thold_daemon.php` is a standalone daemon for high-scalability environments, bypassing the standard poller hook — requires systemd service installation (`service/systemd/thold_daemon.service`). Keep daemon-mode processing logic in sync with the standard poller-hook processing path in `includes/polling.php`.

## Best Practices

1. Keep all new functions and globals under the single `thold_` prefix.
2. Prefer `db_*_prepared()` over string-concatenated SQL.
3. Keep daemon-mode and poller-hook-mode threshold evaluation logic consistent.
4. Wrap all user-facing strings with `__('text', 'thold')`.

## Common Pitfalls to Avoid

```php
// WRONG - concatenated SQL
$sql = "SELECT * FROM plugin_thold_thresholds WHERE id = $id";

// CORRECT
$row = db_fetch_row_prepared('SELECT * FROM plugin_thold_thresholds WHERE id = ?', array($id));
```

## Version Control

Testing changes in a safe environment is crucial, especially when dealing with database interactions and alerting mechanisms. Document all changes in `CHANGELOG.md`.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## Internationalization (i18n)

- Translatable strings are managed with GNU gettext via `locales/build_gettext.sh`. `locales/po/cacti.pot` is the source template; Weblate owns syncing the per-language `.po`/`.mo` files from it.
- When a pull request adds or changes a string wrapped in `__()`/`__n()`/`__esc()`/`__x()`/`__xn()`/`__gettext()`, run `locales/build_gettext.sh` before pushing and add the resulting change to `locales/po/cacti.pot` only. Do not commit the regenerated per-language `.po`/`.mo` files in the same PR — Weblate takes care of the rest.

## References

- [Cacti DB Functions](https://github.com/Cacti/cacti/blob/1.2.x/lib/database.php)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history
