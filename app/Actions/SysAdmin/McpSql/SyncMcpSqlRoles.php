<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpSql;

use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rebuilds the PostgreSQL roles behind MCP SQL from config/mcp_sql_tiers.php and the live
 * schema: one role per tier holding its grants, and one login per set of tiers in use. Tier
 * roles are dropped and recreated in one transaction so a grant the policy no longer names
 * cannot survive. Runs on every deploy after migrations.
 */
class SyncMcpSqlRoles
{
    use AsAction;
    use WithMcpSqlRoleNames;

    public string $commandSignature = 'mcp:sync-sql-roles {--drop : remove every MCP SQL role of this database}';

    /**
     * @return array<string, int>
     */
    public function handle(bool $drop = false): array
    {
        return DB::transaction(function () use ($drop) {
            $roles = collect(DB::select('SELECT rolname, shobj_description(oid, \'pg_authid\') AS tiers FROM pg_roles WHERE starts_with(rolname, ?)', [$this->mcpRolePrefix()]));

            if ($drop) {
                $roles->each(fn ($role) => $this->dropRole($role->rolname));

                return [];
            }

            $roles->filter(fn ($role) => str_starts_with($role->rolname, $this->mcpRolePrefix().'tier_'))
                ->each(fn ($role) => $this->dropRole($role->rolname));

            $counts = $this->createTierRoles();

            $connection = GetMcpSqlConnection::make();
            $roles->filter(fn ($role) => str_starts_with($role->rolname, $this->mcpRolePrefix().'set_'))
                ->each(function ($role) use ($connection) {
                    DB::statement('ALTER ROLE '.$this->quoteIdentifier($role->rolname)." PASSWORD '".$this->setRolePassword($role->rolname)."'");
                    $connection->grantTiers($role->rolname, array_filter(explode(',', (string) $role->tiers)));
                });

            User::where('can_use_mcp', true)->get()
                ->map(fn (User $user) => GetMcpSqlTiers::run($user))
                ->filter()
                ->each(fn (array $tiers) => $connection->ensureSetRole($tiers));

            return $counts;
        });
    }

    /**
     * @return array<string, int> readable relations per tier
     */
    protected function createTierRoles(): array
    {
        $relations = collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'"))->pluck('table_name');
        $hidden    = config('mcp_sql_tiers.hidden_columns');
        $columns   = collect(DB::select("SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ANY(?)", ['{'.implode(',', array_keys($hidden)).'}']))
            ->groupBy('table_name')
            ->map(fn (Collection $rows) => $rows->pluck('column_name')->all());

        $counts = [];
        foreach (config('mcp_sql_tiers.tiers') as $tier => $patterns) {
            $role = $this->createTierRole($tier);

            $tables = $relations->filter(fn (string $table) => $this->matches($table, $patterns)
                && !$this->matches($table, config('mcp_sql_tiers.never'))
                && ($tier !== 'base' || !$this->matches($table, config('mcp_sql_tiers.not_base'))));

            [$partial, $whole] = $tables->partition(fn (string $table) => isset($hidden[$table]));

            foreach ($whole->chunk(200) as $chunk) {
                DB::statement('GRANT SELECT ON '.$chunk->map(fn (string $table) => $this->quoteIdentifier($table))->implode(', ').' TO '.$role);
            }

            foreach ($partial as $table) {
                $this->grantColumns($table, array_diff($columns[$table] ?? [], $hidden[$table]), $role);
            }

            $counts[$tier] = $tables->count();
        }

        foreach (config('mcp_sql_tiers.shown_columns') as $tier => $tables) {
            $role = $this->createTierRole($tier);
            foreach ($tables as $table => $tableColumns) {
                $this->grantColumns($table, array_intersect($tableColumns, $columns[$table] ?? []), $role);
            }
            $counts[$tier] = count($tables);
        }

        return $counts;
    }

    protected function createTierRole(string $tier): string
    {
        $role = $this->quoteIdentifier($this->tierRole($tier));
        DB::statement("CREATE ROLE $role NOLOGIN");

        return $role;
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function grantColumns(string $table, array $columns, string $role): void
    {
        if ($columns === []) {
            return;
        }

        DB::statement('GRANT SELECT ('.implode(', ', array_map(fn (string $column) => $this->quoteIdentifier($column), $columns)).') ON '.$this->quoteIdentifier($table).' TO '.$role);
    }

    protected function dropRole(string $role): void
    {
        DB::statement('DROP OWNED BY '.$this->quoteIdentifier($role));
        DB::statement('DROP ROLE '.$this->quoteIdentifier($role));
    }

    /**
     * @param  list<string>  $patterns
     */
    protected function matches(string $table, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $table)) {
                return true;
            }
        }

        return false;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $counts = $this->handle((bool) $command->option('drop'));

        $command->table(['Tier', 'Relations'], collect($counts)->map(fn (int $count, string $tier) => [$tier, $count])->values()->all());

        return 0;
    }
}
