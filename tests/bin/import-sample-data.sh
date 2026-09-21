#!/bin/bash
# +-------------------------------------------------------------------------+
# | Copyright (C) 2004-2026 The Cacti Group                                 |
# +-------------------------------------------------------------------------+
# | Cacti: The Complete RRDtool-based Graphing Solution                     |
# +-------------------------------------------------------------------------+
# | http://www.cacti.net/                                                   |
# +-------------------------------------------------------------------------+
#
# Imports the thold plugin's sample threshold/graph data fixture into a live
# Cacti installation. Intended to run from CI after the plugin has been
# installed and enabled via cli/plugin_manage.php, and before the poller runs.
#
# Usage: import-sample-data.sh <path-to-cacti-root>

set -e

CACTI_ROOT="$1"

if [ -z "$CACTI_ROOT" ]; then
	echo "Usage: import-sample-data.sh <path-to-cacti-root>" >&2
	exit 1
fi

TESTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

sudo php "$CACTI_ROOT/cli/cli_import.php" --filename="$TESTS_DIR/Fixtures/thold_sample_data.xml"
