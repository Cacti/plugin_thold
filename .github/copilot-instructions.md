# Cacti Thold Plugin AI Instructions

## Project Overview
The `thold` (Thresholds) plugin for Cacti provides fault management by inspecting data from Cacti Graphs and RRDfiles. It supports alerting via Email, Syslog, and SNMP Traps/Informs.

## Architecture & Core Components

### Plugin Structure
- **Entry Point:** `setup.php` registers all Cacti hooks (`api_plugin_register_hook`).
- **Core Logic:** `thold_functions.php` contains the majority of utility functions and business logic.
- **Polling Integration:** `includes/polling.php` hooks into the Cacti poller.
  - `thold_poller_output`: Intercepts poller data to check thresholds.
  - `thold_poller_bottom`: Triggers background processing.
- **Daemon Mode:** `thold_daemon.php` is a standalone daemon for high-scalability environments, bypassing the standard poller hook for processing.
- **Background Processing:** `poller_thold.php` is the CLI script for processing thresholds and maintenance.

### Data Flow
1.  **Data Collection:** Cacti poller collects data.
2.  **Interception:** `thold_poller_output` (in `includes/polling.php`) receives the data.
3.  **Processing:**
    -   **Standard:** Data is processed immediately within the poller hook.
    -   **Daemon:** Data is queued, and `thold_daemon.php` processes it asynchronously.
4.  **Alerting:** If a threshold is breached, `thold_functions.php` handles notification dispatch.

## Developer Workflows

### Installation & Setup
-   **Location:** Code resides in `plugins/thold/` within the Cacti base directory.
-   **Activation:** Install and enable via Cacti Plugin Management.
-   **Daemon:** Requires systemd service installation (`service/systemd/thold_daemon.service`).

### Database Interaction
-   **Abstraction:** Use Cacti's global database functions:
    -   `db_execute_prepared($sql, $params)` for writes.
    -   `db_fetch_assoc($sql)` / `db_fetch_cell($sql)` for reads.
-   **Tables:** All plugin tables are prefixed with `plugin_thold_`.

### Localization
-   Use `__('String', 'thold')` for all user-facing strings to support internationalization.

## Coding Conventions
-   **Naming:** All functions and global variables should be prefixed with `thold_`.
-   **Globals:** Access Cacti configuration via the global `$config` array.
-   **Pathing:** Use `$config['base_path']` for absolute file paths.
-   **Security:** Sanitize inputs using `sanitize_thold_sort_string` or Cacti's input validation functions.

## Key Files
-   `setup.php`: Hook registration.
-   `thold_functions.php`: Shared library of functions.
-   `includes/polling.php`: Poller hooks.
-   `thold_daemon.php`: Service daemon.
-   `includes/settings.php`: Configuration UI logic.
