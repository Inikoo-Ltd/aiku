<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * The machinery every archiver shares: cloning table DDL onto the archive connection, the
 * copy-and-verify step, and the guards (archive must not be the operational database, replication
 * gate that fails closed). The using class declares `public string $archiveConnection`.
 */
trait WithArchiveOperations
{
    /**
     * @return array<int, object{name: string, type: string, not_null: bool}>
     */
    protected function tableColumns($connection, string $table): array
    {
        return $connection->select("
            select a.attname as name, format_type(a.atttypid, a.atttypmod) as type, a.attnotnull as not_null
            from pg_attribute a
            where a.attrelid = ?::regclass and a.attnum > 0 and not a.attisdropped
            order by a.attnum
        ", [$table]);
    }

    /**
     * The operational schema moves on its own schedule, so the archive is reconciled to it on every
     * run rather than only created once: a column added there is added here, and a column dropped
     * there has its NOT NULL lifted here so inserts that no longer carry it still succeed. Columns
     * are never dropped from the archive, because what they hold is the only remaining copy.
     */
    protected function ensureArchiveTable($archive, string $table): void
    {
        $columns = $this->tableColumns(DB::connection(), $table);

        $exists = $archive->selectOne('select to_regclass(?) as found', [$table])->found;

        if (!$exists) {
            $primaryKey = DB::select("
                select a.attname
                from pg_index i
                join pg_attribute a on a.attrelid = i.indrelid and a.attnum = any(i.indkey)
                where i.indrelid = ?::regclass and i.indisprimary
            ", [$table]);

            $definitions = [];
            foreach ($columns as $column) {
                $definitions[] = "\"$column->name\" $column->type".($column->not_null ? ' not null' : '');
            }
            if ($primaryKey) {
                $definitions[] = 'primary key ('.implode(', ', array_map(fn ($col) => "\"$col->attname\"", $primaryKey)).')';
            }

            $archive->statement("create table \"$table\" (".implode(', ', $definitions).')');

            return;
        }

        $archiveColumns = [];
        foreach ($this->tableColumns($archive, $table) as $column) {
            $archiveColumns[$column->name] = $column;
        }

        foreach ($columns as $column) {
            if (!isset($archiveColumns[$column->name])) {
                $archive->statement("alter table \"$table\" add column \"$column->name\" $column->type");
            }
        }

        $sourceNames = array_column($columns, 'name');
        foreach ($archiveColumns as $name => $column) {
            if ($column->not_null && !in_array($name, $sourceNames, true)) {
                $archive->statement("alter table \"$table\" alter column \"$name\" drop not null");
            }
        }
    }

    protected function copyToArchive(string $table, string $keyColumn, array $ids): void
    {
        $archive = DB::connection($this->archiveConnection);

        $archive->table($table)->whereIn($keyColumn, $ids)->delete();

        $buffer = [];
        foreach (DB::table($table)->whereIn($keyColumn, $ids)->cursor() as $row) {
            $buffer[] = (array) $row;
            if (count($buffer) >= 500) {
                $archive->table($table)->insert($buffer);
                $buffer = [];
            }
        }
        if ($buffer) {
            $archive->table($table)->insert($buffer);
        }

        $sourceCount  = DB::table($table)->whereIn($keyColumn, $ids)->count();
        $archiveCount = $archive->table($table)->whereIn($keyColumn, $ids)->count();
        if ($sourceCount !== $archiveCount) {
            throw new Exception("Archive copy verification failed for $table: source $sourceCount vs archive $archiveCount");
        }
    }

    /**
     * Measured from replication slots rather than pg_stat_replication: an inactive slot is what
     * actually pins WAL and fills the disk, and replay_lsn reads as null without pg_monitor rights,
     * which silently made the previous gate report zero lag while a replica was gone. Everything
     * here fails closed, since an unmeasurable gate is indistinguishable from a broken replica.
     */
    protected function replicationState(): ?object
    {
        return DB::selectOne("
            select count(*) as slots,
                   count(*) filter (where not active) as inactive,
                   max(pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)) as retained_bytes
            from pg_replication_slots
            where slot_type = 'physical'
        ");
    }

    protected function assertReplicationMeasurable(): void
    {
        $state = $this->replicationState();

        if (!$state) {
            throw new Exception('Cannot read pg_replication_slots; refusing to archive without a working replication gate.');
        }

        if ($state->slots > 0 && $state->retained_bytes === null) {
            throw new Exception(
                'Replication slots exist but their retained WAL cannot be measured (missing pg_monitor rights?); '.
                'refusing to archive with a gate that would silently report no lag.'
            );
        }
    }

    protected function waitForReplication(?Command $command, ?ProgressBar $progress = null): void
    {
        $maxLagBytes = config('archive.max_replication_lag_mb') * 1024 * 1024;

        while (true) {
            $state = $this->replicationState();

            if (!$state || ($state->slots > 0 && $state->retained_bytes === null)) {
                throw new Exception('Replication gate became unreadable mid-run; stopping before generating more WAL.');
            }

            if ($state->slots == 0) {
                return;
            }

            if ($state->inactive == 0 && (int) $state->retained_bytes <= $maxLagBytes) {
                return;
            }

            $progress?->clear();
            $command?->warn(
                $state->inactive > 0
                    ? "{$state->inactive} replication slot(s) disconnected, waiting: WAL is piling up and will fill the disk"
                    : 'Replicas hold '.round((int) $state->retained_bytes / 1048576).' MB of WAL, waiting for them to catch up'
            );
            $progress?->display();
            sleep(10);
        }
    }

    /**
     * copyToArchive clears its batch on the target before re-inserting, so an archive connection
     * pointing back at the operational database would delete production rows outside any
     * transaction. Same cluster and database and schema is always that mistake.
     */
    protected function assertArchiveIsNotProduction(): void
    {
        if (!config('database.connections.archive.database')) {
            throw new Exception('The archive connection is not configured; set the ARCHIVE_DB_* environment variables.');
        }

        $fingerprint = 'select current_database() as db, current_schema() as schema, system_identifier from pg_control_system()';

        $live    = DB::selectOne($fingerprint);
        $archive = DB::connection($this->archiveConnection)->selectOne($fingerprint);

        if (
            $live->system_identifier === $archive->system_identifier
            && $live->db === $archive->db
            && $live->schema === $archive->schema
        ) {
            throw new Exception(
                'The archive connection resolves to the operational database itself '.
                "({$live->db}.{$live->schema}); refusing to run."
            );
        }
    }
}
