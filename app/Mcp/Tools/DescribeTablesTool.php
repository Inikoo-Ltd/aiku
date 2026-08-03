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

#[Description('Describe the Aiku database schema: list tables matching a name, or get columns, types, foreign keys and the observed values of enum-like columns (type, state, status) for specific tables. Use this before writing SQL instead of querying information_schema by hand.')]
#[IsReadOnly]
class DescribeTablesTool extends Tool
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
            'tables' => ['sometimes', 'array', 'max:10'],
            'search' => ['sometimes', 'string', 'max:64'],
        ]);

        if ($denied = $this->deniedSqlAccess($request)) {
            return $denied;
        }

        $tables = $request->get('tables', []);

        if (empty($tables)) {
            $search = (string) $request->string('search');

            if ($search === '') {
                return Response::error('Provide either tables (array of table names) or search (text to match table names).');
            }

            $matches = DB::connection('aiku_read_only')
                ->table('information_schema.tables')
                ->where('table_schema', 'public')
                ->where('table_name', 'like', '%'.$search.'%')
                ->orderBy('table_name')
                ->limit(60)
                ->pluck('table_name');

            return Response::json([
                'matching_tables' => $matches,
                'hint'            => 'Call again with tables: [...] to see columns and foreign keys.',
            ]);
        }

        $columns = DB::connection('aiku_read_only')
            ->table('information_schema.columns')
            ->whereIn('table_name', $tables)
            ->where('table_schema', 'public')
            ->orderBy('table_name')
            ->orderBy('ordinal_position')
            ->get(['table_name', 'column_name', 'data_type', 'is_nullable']);

        $foreignKeys = DB::connection('aiku_read_only')->select("
            SELECT tc.table_name, kcu.column_name, ccu.table_name AS references_table
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage ccu ON tc.constraint_name = ccu.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public' AND tc.table_name = ANY(?)
        ", ['{'.implode(',', $tables).'}']);

        $schema = [];
        foreach ($columns as $column) {
            $schema[$column->table_name]['columns'][] = [
                'name'     => $column->column_name,
                'type'     => $column->data_type,
                'nullable' => $column->is_nullable === 'YES',
            ];
        }
        $enumStats = DB::connection('aiku_read_only')->select("
            SELECT tablename, attname, array_to_json(most_common_vals::text::text[]) AS vals
            FROM pg_stats
            WHERE schemaname = 'public' AND tablename = ANY(?)
              AND attname IN ('type', 'state', 'status') AND most_common_vals IS NOT NULL
        ", ['{'.implode(',', $tables).'}']);
        foreach ($enumStats as $stat) {
            $schema[$stat->tablename]['enum_values'][$stat->attname] = json_decode($stat->vals);
        }
        foreach ($foreignKeys as $foreignKey) {
            $schema[$foreignKey->table_name]['foreign_keys'][] = $foreignKey->column_name.' → '.$foreignKey->references_table;
        }

        return Response::json([
            'tables'    => $schema,
            'not_found' => array_values(array_diff($tables, array_keys($schema))),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tables' => $schema->array()->description('Table names to describe, max 10')->items($schema->string()),
            'search' => $schema->string()->description('Text to match against table names when you do not know the exact name'),
        ];
    }
}
