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

#[Description('Run a read-only SQL SELECT against the Aiku PostgreSQL database. Only available to users with SQL access enabled. The database is PostgreSQL; discover the schema by querying information_schema.columns. Always add your own LIMIT.')]
#[IsReadOnly]
class SqlQueryTool extends Tool
{
    use WithMcpSqlAccess;

    /**
     * Only offered to users with direct SQL access enabled.
     */
    public function shouldRegister(Request $request): bool
    {
        return (bool) $request->user()?->can_use_mcp_sql;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'sql' => ['required', 'string'],
        ]);

        if ($denied = $this->deniedSqlAccess($request)) {
            return $denied;
        }

        $sql = trim(rtrim(trim($request->string('sql')), ';'));

        if (str_contains($sql, ';')) {
            return Response::error('Only a single statement is allowed.');
        }

        if (!preg_match('/^(select|with)\s/i', $sql)) {
            return Response::error('Only SELECT statements are allowed.');
        }

        $connection = DB::connection('aiku_read_only');

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
            'sql' => $schema->string()->description('A single read-only SQL SELECT statement')->required(),
        ];
    }
}
