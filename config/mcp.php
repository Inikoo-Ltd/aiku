<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

return [
    'sql_timeout_ms' => env('MCP_SQL_TIMEOUT_MS', 180000),
    'sql_max_rows'   => env('MCP_SQL_MAX_ROWS', 2000),

    /*
     * SQL tools refuse to run unless a dedicated read-only database user is
     * configured: without it the aiku_read_only connection falls back to the
     * read-write application user.
     */
    'sql_read_only_user' => env('DB_READ_ONLY_USERNAME'),
];
