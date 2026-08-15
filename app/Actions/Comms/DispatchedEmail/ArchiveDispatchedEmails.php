<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail;

use App\Models\Comms\EmailBulkRunStats;
use App\Models\Comms\MailshotStats;
use App\Models\Comms\OutboxStats;
use App\Models\CRM\CustomerStats;
use App\Models\CRM\Prospect;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveDispatchedEmails
{
    use AsAction;

    public string $commandSignature = 'comms:archive_dispatched_emails {--c|chunk=5000} {--l|limit=} {--d|dry-run}';

    public string $commandDescription = 'Copy dispatched emails older than the retention window to the email archive database, bank their stats baselines and delete them';

    public string $archiveConnection = 'archive';

    private const ADVISORY_LOCK_KEY = 826_041_501;

    public function handle(int $chunkSize = 5000, ?int $limit = null, bool $dryRun = false, ?Command $command = null): int
    {
        $cutoff   = now()->subDays(config('archive.email_retention_days'));
        $children = $this->getChildTables();

        /*
         * Two concurrent runs would pick overlapping batches and contend for the same stats rows.
         * The advisory lock is held by the session, so it clears by itself if the process is killed.
         */
        if (!$dryRun && !DB::selectOne('select pg_try_advisory_lock(?) as locked', [self::ADVISORY_LOCK_KEY])->locked) {
            throw new Exception('Another archive run holds the lock; refusing to start a second one.');
        }

        if ($dryRun) {
            $total = DB::table('dispatched_emails')->where('created_at', '<', $cutoff)->count();
            $command?->info("Dry run: $total dispatched emails older than {$cutoff->toDateString()} would be archived");

            return $total;
        }

        $this->assertArchiveIsNotProduction();
        $this->assertReplicationMeasurable();
        $this->ensureArchiveTables($children);

        $archivedTotal = 0;
        $lastId        = 0;
        while (true) {
            $batchSize = $limit ? min($chunkSize, $limit - $archivedTotal) : $chunkSize;
            if ($batchSize <= 0) {
                break;
            }

            $this->waitForReplication($command);

            /*
             * Resuming from the previous batch's highest id keeps every scan short: without it each
             * batch restarts at the lowest id and re-reads the index entries of everything already
             * deleted, which autovacuum only clears well behind a run of this size. Safe because the
             * cutoff is fixed for the run and rows are taken in ascending id order.
             */
            $dispatchedEmailIds = DB::table('dispatched_emails')
                ->where('created_at', '<', $cutoff)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id')->all();

            if (!$dispatchedEmailIds) {
                break;
            }

            $lastId = end($dispatchedEmailIds);

            $this->copyToArchive('dispatched_emails', 'id', $dispatchedEmailIds);
            foreach ($children as $child) {
                $this->copyToArchive($child->table, $child->fk, $dispatchedEmailIds);
            }
            $this->copyReferencedEmailAddresses($dispatchedEmailIds);

            DB::transaction(function () use ($dispatchedEmailIds, $children) {
                $this->bankStatsBaselines($dispatchedEmailIds);
                foreach ($children as $child) {
                    if (!$child->cascades) {
                        DB::table($child->table)->whereIn($child->fk, $dispatchedEmailIds)->delete();
                    }
                }
                DB::table('dispatched_emails')->whereIn('id', $dispatchedEmailIds)->delete();
            });

            $archivedTotal += count($dispatchedEmailIds);
            $command?->info("Archived $archivedTotal dispatched emails (up to id $lastId)");
        }

        DB::selectOne('select pg_advisory_unlock(?)', [self::ADVISORY_LOCK_KEY]);

        return $archivedTotal;
    }

    /**
     * @return array<int, object{table: string, fk: string, cascades: bool}>
     */
    private function getChildTables(): array
    {
        return DB::select("
            select c.conrelid::regclass::text as \"table\", a.attname as fk, c.confdeltype = 'c' as cascades
            from pg_constraint c
            join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
            where c.contype = 'f' and c.confrelid = 'dispatched_emails'::regclass
            order by 1
        ");
    }

    private function ensureArchiveTables(array $children): void
    {
        $archive = DB::connection($this->archiveConnection);

        $this->ensureArchiveTable($archive, 'dispatched_emails');
        $this->ensureArchiveTable($archive, 'email_addresses');
        foreach ($children as $child) {
            $this->ensureArchiveTable($archive, $child->table);
            $archive->statement("create index if not exists {$child->table}_{$child->fk}_archive_idx on \"{$child->table}\" (\"{$child->fk}\")");
        }
    }

    private function ensureArchiveTable($archive, string $table): void
    {
        $columns = DB::select("
            select a.attname as name, format_type(a.atttypid, a.atttypmod) as type, a.attnotnull as not_null
            from pg_attribute a
            where a.attrelid = ?::regclass and a.attnum > 0 and not a.attisdropped
            order by a.attnum
        ", [$table]);

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

        $archive->statement("create table if not exists \"$table\" (".implode(', ', $definitions).')');
    }

    private function copyToArchive(string $table, string $keyColumn, array $dispatchedEmailIds): void
    {
        $archive = DB::connection($this->archiveConnection);

        $archive->table($table)->whereIn($keyColumn, $dispatchedEmailIds)->delete();

        $buffer = [];
        foreach (DB::table($table)->whereIn($keyColumn, $dispatchedEmailIds)->cursor() as $row) {
            $buffer[] = (array) $row;
            if (count($buffer) >= 500) {
                $archive->table($table)->insert($buffer);
                $buffer = [];
            }
        }
        if ($buffer) {
            $archive->table($table)->insert($buffer);
        }

        $sourceCount  = DB::table($table)->whereIn($keyColumn, $dispatchedEmailIds)->count();
        $archiveCount = $archive->table($table)->whereIn($keyColumn, $dispatchedEmailIds)->count();
        if ($sourceCount !== $archiveCount) {
            throw new Exception("Archive copy verification failed for $table: source $sourceCount vs archive $archiveCount");
        }
    }

    /**
     * Email addresses are a shared lookup table, referenced by live and archived emails alike, so
     * they are copied (never deleted from the operational database) to keep archive reads
     * self-contained: the listing UIs join email_addresses on whichever connection they run.
     */
    private function copyReferencedEmailAddresses(array $dispatchedEmailIds): void
    {
        $emailAddressIds = DB::table('dispatched_emails')
            ->whereIn('id', $dispatchedEmailIds)
            ->whereNotNull('email_address_id')
            ->distinct()
            ->pluck('email_address_id')->all();

        if ($emailAddressIds) {
            $this->copyToArchive('email_addresses', 'id', $emailAddressIds);
        }
    }

    /**
     * Measured from replication slots rather than pg_stat_replication: an inactive slot is what
     * actually pins WAL and fills the disk, and replay_lsn reads as null without pg_monitor rights,
     * which silently made the previous gate report zero lag while a replica was gone. Everything
     * here fails closed, since an unmeasurable gate is indistinguishable from a broken replica.
     */
    private function replicationState(): ?object
    {
        return DB::selectOne("
            select count(*) as slots,
                   count(*) filter (where not active) as inactive,
                   max(pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)) as retained_bytes
            from pg_replication_slots
            where slot_type = 'physical'
        ");
    }

    private function assertReplicationMeasurable(): void
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

    private function waitForReplication(?Command $command): void
    {
        $maxLagBytes = config('archive.email_max_replication_lag_mb') * 1024 * 1024;

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

            $command?->warn(
                $state->inactive > 0
                    ? "{$state->inactive} replication slot(s) disconnected, waiting: WAL is piling up and will fill the disk"
                    : 'Replicas hold '.round((int) $state->retained_bytes / 1048576).' MB of WAL, waiting for them to catch up'
            );
            sleep(10);
        }
    }

    /**
     * copyToArchive clears its batch on the target before re-inserting, so an archive connection
     * pointing back at the operational database would delete production rows outside any
     * transaction. Same cluster and database and schema is always that mistake.
     */
    private function assertArchiveIsNotProduction(): void
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

    /**
     * The counters these baselines feed are recounted from scratch by the hydrators, so the archived
     * figures must be banked in the same transaction as the delete or the first hydration after
     * archiving silently rewrites history (see WithArchivedDispatchedEmails).
     */
    private function bankStatsBaselines(array $dispatchedEmailIds): void
    {
        $outboxIncrements = [];
        $outboxRows       = DB::table('dispatched_emails')
            ->whereIn('id', $dispatchedEmailIds)
            ->selectRaw('outbox_id, state, count(*) as total')
            ->groupBy('outbox_id', 'state')
            ->get();
        foreach ($outboxRows as $row) {
            if (!$row->outbox_id) {
                continue;
            }
            $increments = &$outboxIncrements[$row->outbox_id];
            $increments['number_dispatched_emails']                  = ($increments['number_dispatched_emails'] ?? 0) + $row->total;
            $increments["number_dispatched_emails_state_$row->state"] = ($increments["number_dispatched_emails_state_$row->state"] ?? 0) + $row->total;
        }

        $mailshotIncrements = [];
        $mailshotRows       = DB::table('dispatched_emails')
            ->join('mailshot_has_dispatched_emails', 'mailshot_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id')
            ->whereIn('dispatched_emails.id', $dispatchedEmailIds)
            ->selectRaw('mailshot_id, state, count(*) as total, count(*) filter (where number_reads > 0) as opened, count(*) filter (where number_clicks > 0) as clicked')
            ->groupBy('mailshot_id', 'state')
            ->get();
        foreach ($mailshotRows as $row) {
            $increments = &$mailshotIncrements[$row->mailshot_id];
            $increments['number_dispatched_emails']                   = ($increments['number_dispatched_emails'] ?? 0) + $row->total;
            $increments["number_dispatched_emails_state_$row->state"]  = ($increments["number_dispatched_emails_state_$row->state"] ?? 0) + $row->total;
            $increments['number_delivered_open_success']              = ($increments['number_delivered_open_success'] ?? 0) + $row->opened;
            $increments['number_opened_interact_success']             = ($increments['number_opened_interact_success'] ?? 0) + $row->clicked;
        }

        $bulkRunIncrements = [];
        $bulkRunRows       = DB::table('dispatched_emails')
            ->join('email_bulk_run_has_dispatched_emails', 'email_bulk_run_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id')
            ->whereIn('dispatched_emails.id', $dispatchedEmailIds)
            ->selectRaw('email_bulk_run_id, state, count(*) as total, count(*) filter (where sent_at is not null) as sent, count(*) filter (where number_reads > 0) as opened, count(*) filter (where number_clicks > 0) as clicked')
            ->groupBy('email_bulk_run_id', 'state')
            ->get();
        foreach ($bulkRunRows as $row) {
            $increments = &$bulkRunIncrements[$row->email_bulk_run_id];
            $increments['number_dispatched_emails']                   = ($increments['number_dispatched_emails'] ?? 0) + $row->total;
            $increments["number_dispatched_emails_state_$row->state"]  = ($increments["number_dispatched_emails_state_$row->state"] ?? 0) + $row->total;
            $increments['number_sent_emails']                         = ($increments['number_sent_emails'] ?? 0) + $row->sent;
            $increments['number_opened_emails']                       = ($increments['number_opened_emails'] ?? 0) + $row->opened;
            $increments['number_clicked_emails']                      = ($increments['number_clicked_emails'] ?? 0) + $row->clicked;
        }

        $prospectIncrements = [];
        $prospectRows       = DB::table('prospect_has_dispatched_emails')
            ->whereIn('dispatched_email_id', $dispatchedEmailIds)
            ->selectRaw('prospect_id, count(*) as total')
            ->groupBy('prospect_id')
            ->get();
        foreach ($prospectRows as $row) {
            $prospectIncrements[$row->prospect_id] = ['number_dispatched_emails' => $row->total];
        }

        $this->bankCustomerArchivedEmails($dispatchedEmailIds);

        $this->applyIncrements(Prospect::class, 'id', $prospectIncrements);
        $this->applyIncrements(OutboxStats::class, 'outbox_id', $outboxIncrements);
        $this->applyIncrements(MailshotStats::class, 'mailshot_id', $mailshotIncrements);
        $this->applyIncrements(EmailBulkRunStats::class, 'email_bulk_run_id', $bulkRunIncrements);
    }

    /**
     * Not a stats baseline: no customer counter is recounted from dispatched emails. This records
     * what was moved so the customer email listing can offer the archived ones without reaching
     * across to the archive server on every page load.
     */
    private function bankCustomerArchivedEmails(array $dispatchedEmailIds): void
    {
        $rows = DB::table('customer_has_dispatched_emails')
            ->join('dispatched_emails', 'dispatched_emails.id', '=', 'customer_has_dispatched_emails.dispatched_email_id')
            ->whereIn('dispatched_emails.id', $dispatchedEmailIds)
            ->selectRaw('customer_id, count(*) as total, max(dispatched_emails.created_at) as latest')
            ->groupBy('customer_id')
            ->orderBy('customer_id')
            ->get();

        foreach ($rows as $row) {
            $stats = CustomerStats::where('customer_id', $row->customer_id)->lockForUpdate()->first();

            if (!$stats) {
                continue;
            }

            $archived = $stats->archived_dispatched_emails ?? [];

            $stats->update([
                'archived_dispatched_emails' => [
                    'number_dispatched_emails' => ($archived['number_dispatched_emails'] ?? 0) + $row->total,
                    'last_dispatched_email_at' => max($archived['last_dispatched_email_at'] ?? '', (string) $row->latest),
                ]
            ]);
        }
    }

    private function applyIncrements(string $statsModel, string $ownerColumn, array $incrementsByOwner): void
    {
        if (!$incrementsByOwner) {
            return;
        }

        $owners = array_keys($incrementsByOwner);
        sort($owners);

        foreach ($statsModel::whereIn($ownerColumn, $owners)->orderBy($ownerColumn)->lockForUpdate()->get() as $stats) {
            $archived = $stats->archived_dispatched_emails ?? [];
            foreach ($incrementsByOwner[$stats->{$ownerColumn}] as $key => $increment) {
                $archived[$key] = ($archived[$key] ?? 0) + $increment;
            }
            $stats->update(['archived_dispatched_emails' => $archived]);
        }
    }

    public function asCommand(Command $command): int
    {
        $archived = $this->handle(
            chunkSize: (int) $command->option('chunk'),
            limit: $command->option('limit') ? (int) $command->option('limit') : null,
            dryRun: (bool) $command->option('dry-run'),
            command: $command
        );

        $command->info(($command->option('dry-run') ? 'Would archive' : 'Archived')." $archived dispatched emails");

        return 0;
    }
}
