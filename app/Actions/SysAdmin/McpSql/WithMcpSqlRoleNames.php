<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpSql;

use Illuminate\Support\Facades\DB;

/**
 * Roles are cluster wide while tables belong to one database, so every name carries the
 * database's: test databases sharing a server never touch each other's roles.
 */
trait WithMcpSqlRoleNames
{
    protected function mcpRolePrefix(): string
    {
        return 'mcp_'.substr(preg_replace('/[^a-z0-9_]/', '_', strtolower(DB::connection()->getDatabaseName())), 0, 30).'_';
    }

    protected function tierRole(string $tier): string
    {
        return $this->mcpRolePrefix().'tier_'.$tier;
    }

    /**
     * @param  list<string>  $tiers
     */
    protected function setRole(array $tiers): string
    {
        return $this->mcpRolePrefix().'set_'.substr(md5(implode(',', $tiers)), 0, 10);
    }

    protected function loginsRole(): string
    {
        return $this->mcpRolePrefix().'logins';
    }

    protected function setRolePassword(string $role): string
    {
        return hash_hmac('sha256', $role, (string) config('app.key'));
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    /**
     * @return list<string>
     */
    protected function allTierNames(): array
    {
        return array_merge(array_keys(config('mcp_sql_tiers.tiers')), array_keys(config('mcp_sql_tiers.shown_columns')));
    }
}
