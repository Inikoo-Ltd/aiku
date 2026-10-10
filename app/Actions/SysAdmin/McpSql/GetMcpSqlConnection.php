<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpSql;

use App\Models\SysAdmin\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A restricted user's queries log in as a role that is a member of their tiers and nothing
 * else. Logging in as the full read-only user and switching role would not hold: any SELECT
 * can call set_config('role', ...) and switch straight back.
 */
class GetMcpSqlConnection
{
    use AsAction;
    use WithMcpSqlRoleNames;

    public function handle(User $user): string
    {
        $tiers = GetMcpSqlTiers::run($user);
        if ($tiers === null) {
            return 'aiku_read_only';
        }

        return $this->forTiers($tiers);
    }

    /**
     * @param  list<string>  $tiers
     */
    public function forTiers(array $tiers): string
    {
        $role       = $this->ensureSetRole($tiers);
        $connection = 'mcp_sql_'.$role;

        if (!config("database.connections.$connection")) {
            config(["database.connections.$connection" => array_merge(config('database.connections.aiku_read_only'), [
                'username' => $role,
                'password' => $this->setRolePassword($role),
            ])]);
        }

        return $connection;
    }

    /**
     * @param  list<string>  $tiers
     */
    public function ensureSetRole(array $tiers): string
    {
        $role = $this->setRole($tiers);

        if (DB::selectOne('SELECT 1 AS found FROM pg_roles WHERE rolname = ?', [$role])) {
            return $role;
        }

        try {
            DB::transaction(fn () => $this->createSetRole($role, $tiers));
        } catch (QueryException $e) {
            if ($e->getCode() !== '42710') {
                throw $e;
            }
        }

        return $role;
    }

    /**
     * @param  list<string>  $tiers
     */
    protected function createSetRole(string $role, array $tiers): void
    {
        $logins = $this->loginsRole();
        if (!DB::selectOne('SELECT 1 AS found FROM pg_roles WHERE rolname = ?', [$logins])) {
            DB::statement('CREATE ROLE '.$this->quoteIdentifier($logins).' NOLOGIN');
        }

        DB::statement('CREATE ROLE '.$this->quoteIdentifier($role)." LOGIN PASSWORD '".$this->setRolePassword($role)."' CONNECTION LIMIT 20 IN ROLE ".$this->quoteIdentifier($logins));
        DB::statement('COMMENT ON ROLE '.$this->quoteIdentifier($role).' IS '.DB::getPdo()->quote(implode(',', $tiers)));

        $this->grantTiers($role, $tiers);
    }

    /**
     * Tier roles that do not exist yet are skipped: the next sync grants them from the role's comment.
     *
     * @param  list<string>  $tiers
     */
    public function grantTiers(string $role, array $tiers): void
    {
        $tierRoles = array_map(fn (string $tier) => $this->tierRole($tier), $tiers);
        $existing  = collect(DB::select('SELECT rolname FROM pg_roles WHERE rolname = ANY(?)', ['{'.implode(',', $tierRoles).'}']))->pluck('rolname');

        foreach ($existing as $tierRole) {
            DB::statement('GRANT '.$this->quoteIdentifier($tierRole).' TO '.$this->quoteIdentifier($role));
        }
    }
}
