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
use App\Actions\Traits\WithArchiveOperations;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveDispatchedEmails
{
    use AsAction;
    use WithArchiveOperations;

    public string $commandSignature = 'comms:archive_dispatched_emails {--c|chunk=5000} {--l|limit=} {--d|dry-run} {--from=} {--until=}';

    public string $commandDescription = 'Copy dispatched emails older than the retention window to the email archive database, bank their stats baselines and delete them';

    public string $archiveConnection = 'archive';

    private const ADVISORY_LOCK_KEY = 826_041_501;

    public function handle(
        int $chunkSize = 5000,
        ?int $limit = null,
        ?string $from = null,
        ?string $until = null,
        bool $dryRun = false,
        ?Command $command = null
    ): int {
        /*
         * --until can only ever bring the cutoff forward. Letting it push the cutoff past the
         * retention window would archive emails that are supposed to stay in the operational
         * database, which no later run would put back.
         */
        $cutoff = now()->subDays(config('archive.email_retention_days'));
        if ($until && Carbon::parse($until)->lt($cutoff)) {
            $cutoff = Carbon::parse($until);
        }

        $children = $this->getChildTables();

        /*
         * Runs over the same rows would pick overlapping batches and contend for the same stats
         * rows, so each range takes its own lock: identical ranges still refuse to double-start,
         * while disjoint ranges run side by side. Held by the session, so a killed process frees it.
         *
         * Nothing here can verify that ranges given to separate processes are actually disjoint —
         * that is the operator's responsibility.
         */
        $lockKey = $from || $until ? crc32(self::ADVISORY_LOCK_KEY.'|'.$from.'|'.$until) : self::ADVISORY_LOCK_KEY;

        if (!$dryRun && !DB::selectOne('select pg_try_advisory_lock(?) as locked', [$lockKey])->locked) {
            throw new Exception('Another archive run holds the lock for this range; refusing to start a second one.');
        }

        if ($dryRun) {
            $total = DB::table('dispatched_emails')
                ->where('created_at', '<', $cutoff)
                ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
                ->count();
            $range = $from ? " from {$from}" : '';
            $command?->info("Dry run: $total dispatched emails older than {$cutoff->toDateString()}$range would be archived");

            return $total;
        }

        $this->assertArchiveIsNotProduction();
        $this->assertReplicationMeasurable();
        $this->ensureArchiveTables($children);

        $progress = null;
        if ($command) {
            $eligible = DB::table('dispatched_emails')
                ->where('created_at', '<', $cutoff)
                ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
                ->count();

            if ($eligible === 0) {
                if (!$dryRun) {
                    DB::selectOne('select pg_advisory_unlock(?)', [$lockKey]);
                }
                $command->info('Nothing older than '.$cutoff->toDateString().' (retention '.config('archive.email_retention_days').' days)');

                return 0;
            }

            $progress = $command->getOutput()->createProgressBar($limit ? min($limit, $eligible) : $eligible);
            $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%%  elapsed %elapsed:6s%  eta %estimated:-6s%  %message%');
            $progress->setMessage('');
            $progress->start();
        }

        $archivedTotal  = 0;
        $lastId         = 0;
        $lastCreatedAt  = $from ?: '1970-01-01';
        while (true) {
            $batchSize = $limit ? min($chunkSize, $limit - $archivedTotal) : $chunkSize;
            if ($batchSize <= 0) {
                break;
            }

            $this->waitForReplication($command, $progress);

            /*
             * Ordered by (created_at, id) to match the index of the same name: ordering by id alone
             * cannot use it, so the planner walks the primary key and reads every row from disk just
             * to test its date, which grows steadily worse as the remaining rows thin out. The pair
             * is also the resume cursor, so no batch rescans what an earlier one already took.
             * created_at is not unique, hence the row comparison rather than a plain column one.
             */
            $rows = DB::table('dispatched_emails')
                ->where('created_at', '<', $cutoff)
                ->whereRaw('(created_at, id) > (?, ?)', [$lastCreatedAt, $lastId])
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($batchSize)
                ->get(['id', 'created_at']);

            if ($rows->isEmpty()) {
                break;
            }

            $dispatchedEmailIds = $rows->pluck('id')->all();
            $lastCreatedAt      = $rows->last()->created_at;
            $lastId             = $rows->last()->id;

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
            $progress?->setMessage(Carbon::parse($lastCreatedAt)->format('M Y'));
            $progress?->advance(count($dispatchedEmailIds));
        }

        $progress?->finish();
        $command?->newLine();

        DB::selectOne('select pg_advisory_unlock(?)', [$lockKey]);

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

        if (!$emailAddressIds) {
            return;
        }

        /*
         * Unlike everything else here this table is shared: workers split on dispatched emails cover
         * disjoint rows there but heavily overlapping addresses, since one person is written to over
         * many years. So it is never cleared and never rewritten — only the missing rows are added,
         * tolerating another worker inserting the same one at the same moment. Verification asks
         * whether every referenced address is present rather than comparing counts, which two
         * workers touching the same rows can never agree on.
         */
        $archive = DB::connection($this->archiveConnection);

        $missing = array_values(array_diff(
            $emailAddressIds,
            $archive->table('email_addresses')->whereIn('id', $emailAddressIds)->pluck('id')->all()
        ));

        if ($missing) {
            $buffer = [];
            foreach (DB::table('email_addresses')->whereIn('id', $missing)->cursor() as $row) {
                $buffer[] = (array) $row;
                if (count($buffer) >= 500) {
                    $archive->table('email_addresses')->insertOrIgnore($buffer);
                    $buffer = [];
                }
            }
            if ($buffer) {
                $archive->table('email_addresses')->insertOrIgnore($buffer);
            }
        }

        $present = $archive->table('email_addresses')->whereIn('id', $emailAddressIds)->count();
        if ($present !== count($emailAddressIds)) {
            throw new Exception(
                'Archive is missing '.(count($emailAddressIds) - $present).' of '.count($emailAddressIds).' referenced email addresses'
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
            from: $command->option('from') ?: null,
            until: $command->option('until') ?: null,
            dryRun: (bool) $command->option('dry-run'),
            command: $command
        );

        $command->info(($command->option('dry-run') ? 'Would archive' : 'Archived')." $archived dispatched emails");
        $command->info('Run VACUUM (ANALYZE) dispatched_emails; before starting another large run.');

        return 0;
    }
}
