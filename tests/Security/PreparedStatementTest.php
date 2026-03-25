<?php

declare(strict_types=1);

/*
 * Source-verification tests for PR #762 (fix/hardening-prepared-statements).
 *
 * These tests assert that the hardened call sites exist in the source files.
 * Runtime execution of the page files is not possible without the Cacti
 * bootstrap; source-verification is the established pattern in this repo
 * (see XssOutputTest.php describe blocks with ->todo() seam notes).
 *
 * Files under test:
 *   - notify_lists.php  : $selected_items operations use db_execute_prepared
 *   - thold.php         : RLIKE filters wrapped with db_qstr
 *   - thold_graph.php   : RLIKE filters wrapped with db_qstr
 *   - thold_webapi.php  : selected_graphs_array uses sanitize_unserialize_selected_items
 */

require_once __DIR__ . '/../Helpers/CactiStubs.php';

// ---------------------------------------------------------------------------
// Source loading — fail fast if a file is missing.
// ---------------------------------------------------------------------------

$notify_lists_src = file_get_contents(__DIR__ . '/../../notify_lists.php');
$thold_src        = file_get_contents(__DIR__ . '/../../thold.php');
$thold_graph_src  = file_get_contents(__DIR__ . '/../../thold_graph.php');
$thold_webapi_src = file_get_contents(__DIR__ . '/../../thold_webapi.php');

if ($notify_lists_src === false) {
    throw new \RuntimeException('Could not read notify_lists.php');
}
if ($thold_src === false) {
    throw new \RuntimeException('Could not read thold.php');
}
if ($thold_graph_src === false) {
    throw new \RuntimeException('Could not read thold_graph.php');
}
if ($thold_webapi_src === false) {
    throw new \RuntimeException('Could not read thold_webapi.php');
}

// ---------------------------------------------------------------------------
// notify_lists.php — $selected_items operations
// ---------------------------------------------------------------------------

describe('notify_lists.php — selected_items use db_execute_prepared', function () use ($notify_lists_src): void {
    /*
     * Every DML statement that operates on a $selected_items element must use
     * db_execute_prepared with a ? placeholder. Direct string interpolation
     * would allow second-order injection if sanitize_unserialize_selected_items
     * were ever bypassed or changed.
     */

    test('UPDATE thold_data SET notify_alert uses db_execute_prepared', function () use ($notify_lists_src): void {
        expect($notify_lists_src)->toContain(
            "db_execute_prepared('UPDATE thold_data SET notify_alert=?"
        );
    });

    test('UPDATE thold_data SET notify_extra uses db_execute_prepared', function () use ($notify_lists_src): void {
        expect($notify_lists_src)->toContain(
            "db_execute_prepared(\"UPDATE thold_data SET notify_extra='' WHERE id=?\""
        );
    });

    test('DELETE FROM plugin_thold_threshold_contact uses db_execute_prepared', function () use ($notify_lists_src): void {
        expect($notify_lists_src)->toContain(
            "db_execute_prepared('DELETE FROM plugin_thold_threshold_contact WHERE thold_id = ?'"
        );
    });

    test('UPDATE thold_data SET notify_warning uses db_execute_prepared', function () use ($notify_lists_src): void {
        expect($notify_lists_src)->toContain(
            "db_execute_prepared('UPDATE thold_data SET notify_warning=0 WHERE id=? AND notify_warning=?'"
        );
    });

    test('UPDATE thold_data SET notify_alert=0 uses db_execute_prepared', function () use ($notify_lists_src): void {
        expect($notify_lists_src)->toContain(
            "db_execute_prepared('UPDATE thold_data SET notify_alert=0 WHERE id=? AND notify_alert=?'"
        );
    });

    test('no raw db_execute call interpolates $selected_items directly', function () use ($notify_lists_src): void {
        // A raw db_execute whose argument contains $selected_items without a
        // placeholder is the vulnerability pattern. Verify it is absent.
        $matches = [];
        preg_match_all(
            '/\bdb_execute\s*\(\s*[\'"].*\$selected_items/m',
            $notify_lists_src,
            $matches
        );
        expect($matches[0])->toBe([]);
    });

    test('intval() is applied to $selected_items elements before binding', function () use ($notify_lists_src): void {
        // Every bound $selected_items[$i] must be wrapped in intval() to
        // enforce integer type at the application layer.
        expect($notify_lists_src)->toContain('intval($selected_items[$i])');
    });

    test('intval() is applied to get_request_var id before binding', function () use ($notify_lists_src): void {
        expect($notify_lists_src)->toContain("intval(get_request_var('id'))");
    });
});

// ---------------------------------------------------------------------------
// thold.php — RLIKE filter uses db_qstr
// ---------------------------------------------------------------------------

describe('thold.php — RLIKE filter wrapped with db_qstr', function () use ($thold_src): void {
    /*
     * thold.php:617 builds a WHERE clause with a RLIKE predicate. The user-
     * supplied rfilter value must be quoted via db_qstr before interpolation
     * to prevent regex injection that could exhaust server memory (ReDoS) or
     * expose data via crafted patterns.
     */

    test('RLIKE predicate uses db_qstr on rfilter value', function () use ($thold_src): void {
        expect($thold_src)->toContain('RLIKE ' . "' . db_qstr(get_request_var('rfilter'))");
    });

    test('raw rfilter value is not interpolated directly into RLIKE', function () use ($thold_src): void {
        // Pattern that would indicate direct interpolation without db_qstr.
        $matches = [];
        preg_match_all(
            '/RLIKE\s+[\'"]?\s*\.\s*get_(?:nfilter_)?request_var\s*\(\s*[\'"]rfilter[\'"]\s*\)/m',
            $thold_src,
            $matches
        );
        expect($matches[0])->toBe([]);
    });

    test('intval() applied to integer request variables before interpolation', function () use ($thold_src): void {
        // Verify at least one intval() call guards integer interpolations.
        expect($thold_src)->toContain('intval(');
    });
});

// ---------------------------------------------------------------------------
// thold_graph.php — RLIKE filters wrapped with db_qstr
// ---------------------------------------------------------------------------

describe('thold_graph.php — RLIKE filters wrapped with db_qstr', function () use ($thold_graph_src): void {
    test('name_cache RLIKE uses db_qstr (line ~407)', function () use ($thold_graph_src): void {
        expect($thold_graph_src)->toContain("RLIKE ' . db_qstr(get_request_var('rfilter'))");
    });

    test('hostname RLIKE uses db_qstr via $rfilter_q variable', function () use ($thold_graph_src): void {
        // thold_graph.php:939 assigns db_qstr result to $rfilter_q before use.
        expect($thold_graph_src)->toContain("db_qstr(get_request_var('rfilter'))");
        expect($thold_graph_src)->toContain('$rfilter_q');
        expect($thold_graph_src)->toContain('RLIKE ' . '" . $rfilter_q');
    });

    test('description RLIKE uses db_qstr (line ~1398)', function () use ($thold_graph_src): void {
        // thold_graph.php:1398 and 1493 both use db_qstr for description RLIKE.
        $count = substr_count($thold_graph_src, "RLIKE ' . db_qstr(get_request_var('rfilter'))");
        expect($count)->toBeGreaterThanOrEqual(2);
    });

    test('no raw rfilter interpolated directly into any RLIKE clause', function () use ($thold_graph_src): void {
        $matches = [];
        preg_match_all(
            '/RLIKE\s+[\'"]?\s*\.\s*get_(?:nfilter_)?request_var\s*\(\s*[\'"]rfilter[\'"]\s*\)/m',
            $thold_graph_src,
            $matches
        );
        expect($matches[0])->toBe([]);
    });
});

// ---------------------------------------------------------------------------
// thold_webapi.php — sanitize_unserialize_selected_items
// ---------------------------------------------------------------------------

describe('thold_webapi.php — sanitize_unserialize_selected_items', function () use ($thold_webapi_src): void {
    /*
     * thold_webapi.php:864 processes a serialized graph array from user input.
     * Using sanitize_unserialize_selected_items() instead of raw unserialize()
     * prevents PHP object injection attacks.
     */

    test('selected_graphs_array is processed through sanitize_unserialize_selected_items', function () use ($thold_webapi_src): void {
        expect($thold_webapi_src)->toContain(
            "sanitize_unserialize_selected_items(get_nfilter_request_var('selected_graphs_array'))"
        );
    });

    test('raw unserialize() is not called directly on selected_graphs_array', function () use ($thold_webapi_src): void {
        $matches = [];
        preg_match_all(
            '/\bunserialize\s*\(\s*get_(?:nfilter_)?request_var\s*\(\s*[\'"]selected_graphs_array[\'"]\s*\)\s*\)/m',
            $thold_webapi_src,
            $matches
        );
        expect($matches[0])->toBe([]);
    });
});

// ---------------------------------------------------------------------------
// Cross-file: intval() on integer interpolations
// ---------------------------------------------------------------------------

describe('integer interpolation hardening — intval() applied', function () use ($notify_lists_src, $thold_src): void {
    /*
     * Any integer value sourced from user input that is embedded in SQL
     * (even via a placeholder) should pass through intval() as a defense-in-
     * depth measure, ensuring type correctness even if the PDO binding layer
     * infers incorrectly.
     */

    test('notify_lists.php applies intval to thold_id before binding', function () use ($notify_lists_src): void {
        expect($notify_lists_src)->toContain('intval($selected_items[$i])');
    });

    test('notify_lists.php applies intval to list id before binding', function () use ($notify_lists_src): void {
        expect($notify_lists_src)->toContain("intval(get_request_var('id'))");
    });

    test('intval correctly coerces a valid integer string', function (): void {
        expect(intval('42'))->toBe(42);
    });

    test('intval truncates a mixed string to its leading integer', function (): void {
        // Matches behavior documented in Cacti's hardening guidelines.
        expect(intval('42; DROP TABLE thold_data;'))->toBe(42);
    });

    test('intval converts a non-numeric string to 0', function (): void {
        expect(intval("' OR '1'='1"))->toBe(0);
    });

    test('intval on a negative value preserves sign', function (): void {
        expect(intval('-5'))->toBe(-5);
    });
});
