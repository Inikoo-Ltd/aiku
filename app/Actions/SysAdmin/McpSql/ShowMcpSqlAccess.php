<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpSql;

use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowMcpSqlAccess
{
    use AsAction;
    use WithMcpSqlRoleNames;

    public string $commandSignature = 'mcp:sql-access {username}';

    /**
     * What a user's MCP SQL can read, checked against PostgreSQL itself rather than the policy.
     *
     * @return array{tiers: list<string>|null, role: string|null, readable_relations: int|null, readable_never: list<string>, readable_hidden_columns: list<string>}
     */
    public function handle(User $user): array
    {
        $tiers = GetMcpSqlTiers::run($user);
        if ($tiers === null) {
            return ['tiers' => null, 'role' => null, 'readable_relations' => null, 'readable_never' => [], 'readable_hidden_columns' => []];
        }

        $role = GetMcpSqlConnection::make()->ensureSetRole($tiers);

        $readable = collect(DB::select(
            "SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relkind IN ('r', 'v', 'm', 'p')
               AND (has_table_privilege(?, c.oid, 'SELECT') OR has_any_column_privilege(?, c.oid, 'SELECT'))",
            [$role, $role]
        ))->pluck('relname');

        $readableHidden = [];
        foreach (config('mcp_sql_tiers.hidden_columns') as $table => $columns) {
            foreach ($columns as $column) {
                $exists = DB::selectOne("SELECT 1 AS found FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?", [$table, $column]);
                if ($exists && DB::selectOne('SELECT has_column_privilege(?, ?, ?, \'SELECT\') AS allowed', [$role, $table, $column])->allowed) {
                    $readableHidden[] = "$table.$column";
                }
            }
        }

        return [
            'tiers'                   => $tiers,
            'role'                    => $role,
            'readable_relations'      => $readable->count(),
            'readable_never'          => $readable->filter(fn (string $table) => collect(config('mcp_sql_tiers.never'))->contains(fn (string $pattern) => fnmatch($pattern, $table)))->values()->all(),
            'readable_hidden_columns' => $readableHidden,
        ];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $user = User::where('username', $command->argument('username'))->firstOrFail();
        $access = $this->handle($user);

        if ($access['tiers'] === null) {
            $command->info($user->username.' is unrestricted: every table and column.');

            return 0;
        }

        $command->info('Tiers: '.implode(', ', $access['tiers']));
        $command->info('Login role: '.$access['role']);
        $command->info('Readable tables and views: '.$access['readable_relations']);
        $command->line('"Never" tables readable: '.(implode(', ', $access['readable_never']) ?: 'none'));
        $command->line('Hidden columns readable: '.(implode(', ', $access['readable_hidden_columns']) ?: 'none'));

        return 0;
    }
}
