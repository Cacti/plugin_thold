# Cacti Thold Plugin AI Instructions

## Project Overview
The `thold` (Thresholds) plugin for Cacti provides fault management by inspecting data from Cacti Graphs and RRDfiles. It supports alerting via Email, Syslog, and SNMP Traps/Informs.

## Architecture & Core Components

### Technolgoy Stack
The plugin is developed in PHP and integrates tightly with the Cacti monitoring platform. It leverages Cacti's existing database abstraction layer and plugin architecture.
In this repo, the code adheres to PHP PSR-12 coding standards and best practices supporting modern PHP versions (PHP 8.1 and above).

## Testing Frameworks
While there is no dedicated testing framework integrated into the plugin, We will be leveraging PHPUnit for unit testing and integration tests. Tests should be written to cover critical functionalities, especially around threshold evaluations and alerting mechanisms.
For now test cases needing a database can be tested using a local Cacti installation with the thold plugin enabled but non database dependent logic should be unit tested using PHPUnit.
Tests should be setup so github actions can run them  automatically on pull requests in the future.
create a tests/ directory in the root of the project to hold test cases.

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


## Important Notes
-   The plugin relies heavily on Cacti's built-in functions and architecture. Familiarity with Cacti development practices is essential.
-   Testing changes in a safe environment is crucial, especially when dealing with database interactions and alerting mechanisms.
- Some Database functions are included from the cacti project. Here are some of the commonly used functions:

## you can find the included file in the cacti project here:
- [Cacti DB Functions](https://github.com/Cacti/cacti/blob/1.2.x/lib/database.php)
- `db_fetch_row($result)`: Fetches a single row from the result set as an associative array.
- `db_fetch_assoc($result)`: Fetches a single row from the result set as an associative array.
- `db_query($query)`: Executes a SQL query and returns the result set.
- `db_insert($table, $data)`: Inserts a new record into the specified table.
- `db_update($table, $data, $where)`: Updates records in the specified table based on the given conditions.
- `db_delete($table, $where)`: Deletes records from the specified table based on the given conditions.
- `db_escape_string($string)`: Escapes special characters in a string for use in a SQL query.
- `db_num_rows($result)`: Returns the number of rows in the result set.
- `db_last_insert_id()`: Retrieves the ID of the last inserted record.



##web documentation
- [Cacti Documentation](https://www.github.com/Cacti/documentation)

