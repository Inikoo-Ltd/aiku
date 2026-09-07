<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Run a read-only SQL SELECT against the Aiku PostgreSQL database, or against the NightOwl telemetry database (requests, exceptions, queries, jobs, logs) by passing database: nightowl, or against the archive database by passing database: archive. Only available to users with SQL access enabled. Use describe-tables-tool to discover schema. Always add your own LIMIT. Gotchas: never guess the values of enum-like columns (type, state, status) — describe-tables-tool reports their actual values; the legacy *_intervals tables were dropped — use *_time_series and *_time_series_records instead; filter suppliers by country via their address country_id, not by matching location text; text columns use a nondeterministic collation so ILIKE and regex fail — write col COLLATE "C" ILIKE ... instead. Per SKU stock history older than the retention window lives only in the archive: org_stock_histories and location_org_stock_histories keep the last three years daily plus one snapshot per month before that, and every other day is in database: archive with the same columns — query both and union when a question spans years. Read the aiku data guide resource before writing revenue or stock history queries.')]
#[IsReadOnly]
class SqlQueryTool extends Tool
{
    use WithMcpSqlAccess;

    public function handle(Request $request): Response
    {
        $request->validate([
            'sql'      => ['required', 'string'],
            'database' => ['sometimes', 'string', 'in:aiku,nightowl,archive'],
        ]);

        if ($denied = $this->deniedSqlAccess($request)) {
            return $denied;
        }

        if ($denied = $this->deniedArchiveAccess($request)) {
            return $denied;
        }

        $sql = trim(rtrim(trim($request->string('sql')), ';'));

        if (str_contains(preg_replace("/'(?:[^']|'')*'/", '', $sql), ';')) {
            return Response::error('Only a single statement is allowed.');
        }

        if (!preg_match('/^(select|with)\s/i', $sql)) {
            return Response::error('Only SELECT statements are allowed.');
        }

        $connection = DB::connection($this->resolveSqlConnection($request));

        try {
            $rows = $connection->transaction(function () use ($connection, $sql) {
                $connection->statement('SET TRANSACTION READ ONLY');
                $connection->statement('SET LOCAL statement_timeout = '.(int) config('mcp.sql_timeout_ms'));

                return $connection->select($sql);
            });
        } catch (Throwable $e) {
            return Response::error('Query failed: '.$e->getMessage());
        }

        $maxRows = (int) config('mcp.sql_max_rows');

        return Response::json([
            'row_count' => count($rows),
            'truncated' => count($rows) > $maxRows,
            'rows'      => array_slice($rows, 0, $maxRows),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sql'      => $schema->string()->description('A single read-only SQL SELECT statement')->required(),
            'database' => $schema->string()->enum(['aiku', 'nightowl', 'archive'])->description('Database to query: aiku (default, commerce data), nightowl (telemetry: requests, exceptions, queries, jobs) or archive (what aiku no longer keeps: dispatched emails, audits, and per SKU stock history older than the retention window)'),
        ];
    }
}
