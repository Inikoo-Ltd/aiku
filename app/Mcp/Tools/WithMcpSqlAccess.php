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
        'archive'  => 'archive',
    ];

    protected function resolveSqlConnection(Request $request): string
    {
        return $this->sqlDatabases[$request->string('database', 'aiku')->toString()];
    }

    /**
     * The archive is a separate server holding what the operational database no longer keeps
     * (dispatched emails, audits, per SKU stock history older than the retention window). A query
     * against it is only meaningful where it is configured, and an unreachable one has to say so
     * rather than come back empty and read as "no such data".
     */
    protected function deniedArchiveAccess(Request $request): ?Response
    {
        if ($request->string('database', 'aiku')->toString() !== 'archive') {
            return null;
        }

        if (blank(config('database.connections.archive.database'))) {
            return Response::error('The archive database is not configured in this environment; only aiku and nightowl are available here.');
        }

        return null;
    }

    protected function deniedSqlAccess(Request $request): ?Response
    {
        if (blank(config('mcp.sql_read_only_user'))) {
            return Response::error('SQL access is disabled: this environment has no dedicated read-only database user configured.');
        }

        if (!$request->user()?->can_use_mcp_sql) {
            return Response::error('SQL access is not enabled for this user. Do not retry: ask a sysadmin to enable it, and meanwhile answer with the purpose-built tools (call my-access-tool to see what you can reach).');
        }

        return null;
    }

    /**
     * Assistants cache the tool list, so one connected before the write tools shipped only sees SQL
     * and concludes aiku refuses the change. The hint rides on answers it does get.
     */
    protected function orgStockWriteToolsHint(Request $request, string $subject): ?string
    {
        if (!$request->user()?->can_use_mcp_discontinue || !str_contains(strtolower($subject), 'org_stock')) {
            return null;
        }

        return 'SQL is read-only. This user is enrolled to change the state of SKOs (discontinue, suspend, back to active): use org-stock-discontinue-preview-tool, then org-stock-discontinue-tool after they confirm. If those tools are not in your tool list, your copy of the aiku tools is out of date: tell the user to refresh the aiku connector in their assistant settings and allow its write actions. Aiku permissions are not the problem.';
    }
}
