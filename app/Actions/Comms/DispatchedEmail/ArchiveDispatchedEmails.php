<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail;

use App\Models\Comms\EmailBulkRunStats;
use App\Models\Comms\MailshotStats;
use App\Models\Comms\OutboxStats;
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

    public function handle(int $chunkSize = 5000, ?int $limit = null, bool $dryRun = false, ?Command $command = null): int
    {
        $cutoff   = now()->subDays(config('archive.email_retention_days'));
        $children = $this->getChildTables();

        if ($dryRun) {
            $total = DB::table('dispatched_emails')->where('created_at', '<', $cutoff)->count();
            $command?->info("Dry run: $total dispatched emails older than {$cutoff->toDateString()} would be archived");

            return $total;
        }

        $this->ensureArchiveTables($children);

        $archivedTotal = 0;
        while (true) {
            $batchSize = $limit ? min($chunkSize, $limit - $archivedTotal) : $chunkSize;
            if ($batchSize <= 0) {
                break;
            }

            $this->waitForReplication($command);

            $dispatchedEmailIds = DB::table('dispatched_emails')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id')->all();

            if (!$dispatchedEmailIds) {
                break;
            }

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
            $command?->info("Archived $archivedTotal dispatched emails (up to id ".end($dispatchedEmailIds).')');
        }

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

    private function waitForReplication(?Command $command): void
    {
        $maxLagBytes = config('archive.email_max_replication_lag_mb') * 1024 * 1024;

        while (true) {
            $lag = (int) DB::selectOne('
                select coalesce(max(pg_wal_lsn_diff(pg_current_wal_lsn(), replay_lsn)), 0) as lag
                from pg_stat_replication
            ')->lag;

            if ($lag <= $maxLagBytes) {
                return;
            }

            $command?->warn('Replication lag '.round($lag / 1048576).' MB, waiting for replicas to catch up');
            sleep(10);
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

        $this->applyIncrements(OutboxStats::class, 'outbox_id', $outboxIncrements);
        $this->applyIncrements(MailshotStats::class, 'mailshot_id', $mailshotIncrements);
        $this->applyIncrements(EmailBulkRunStats::class, 'email_bulk_run_id', $bulkRunIncrements);
    }

    private function applyIncrements(string $statsModel, string $ownerColumn, array $incrementsByOwner): void
    {
        if (!$incrementsByOwner) {
            return;
        }

        foreach ($statsModel::whereIn($ownerColumn, array_keys($incrementsByOwner))->lockForUpdate()->get() as $stats) {
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
