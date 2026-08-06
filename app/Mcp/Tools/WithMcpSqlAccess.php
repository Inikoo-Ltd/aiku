<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait WithMcpSqlAccess
{
    /** @var array<string, string> */
    protected array $sqlDatabases = [
        'aiku'     => 'aiku_read_only',
        'nightowl' => 'nightowl',
    ];

    protected function resolveSqlConnection(Request $request): string
    {
        return $this->sqlDatabases[$request->string('database', 'aiku')->toString()];
    }

    protected function deniedSqlAccess(Request $request): ?Response
    {
        if (blank(config('mcp.sql_read_only_user'))) {
            return Response::error('SQL access is disabled: this environment has no dedicated read-only database user configured.');
        }

        if (!$request->user()?->can_use_mcp_sql) {
            return Response::error('SQL access is not enabled for this user.');
        }

        return null;
    }
}
