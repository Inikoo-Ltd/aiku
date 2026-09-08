<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fills the picking and packing lifecycle timestamps for rows written before the instrumentation
 * went live.
 *
 * Every value copied here is one the application already holds, never a reconstruction:
 *
 * - pickings.last_picked_at and packings.done_at come from the row's own created_at. Both tables
 *   are only ever written by the synchronous device actions, so the insert is the confirmation.
 * - packings.queued_at and packings.packing_at come from delivery_notes.picked_at and .packing_at,
 *   the same source the live code reads.
 *
 * Only work done after the shop moved to Aiku is touched, and only on notes Aiku raised itself.
 * Before the move orders were picked on paper and keyed in afterwards, and for some weeks after it
 * a dwindling tail of old orders kept arriving from Aurora and was worked the same way, so no
 * timestamp on either reflects how long anything took.
 *
 * Rows with no source are left null rather than filled with a guess: packings whose delivery note
 * has no timestamps of its own, and pickings of type not-pick, which were never picked at all.
 */
class BackfillWarehouseTimestamps
{
    use AsAction;

    private const int CHUNK_SIZE = 5000;

    public string $commandSignature = 'dispatching:backfill-warehouse-timestamps
        {--dry-run : Report what would be written without writing}';

    /**
     * @return array<string, int>
     */
    public function handle(bool $dryRun = false): array
    {
        $counts = [
            'last_picked_at'    => (clone $this->pickingsToFill())->count(),
            'done_at'           => (clone $this->packingsToFill())->count(),
            'note_level'        => (clone $this->noteLevelToFill())->count(),
            'skipped_pre_aiku'  => $this->countPreMigration(),
            'skipped_no_source' => $this->countWithoutNoteSource(),
        ];

        if ($dryRun) {
            return $counts;
        }

        $this->fill($this->pickingsToFill(...), 'pickings', fn (array $ids) => DB::table('pickings')
            ->whereIn('id', $ids)
            ->update(['last_picked_at' => DB::raw('created_at')]));

        $this->fill($this->packingsToFill(...), 'packings', fn (array $ids) => DB::table('packings')
            ->whereIn('id', $ids)
            ->update(['done_at' => DB::raw('created_at')]));

        $this->fill($this->noteLevelToFill(...), 'packings', fn (array $ids) => DB::statement('
            UPDATE packings
            SET queued_at  = delivery_notes.picked_at,
                packing_at = delivery_notes.packing_at
            FROM delivery_notes
            WHERE delivery_notes.id = packings.delivery_note_id
              AND packings.id = ANY(?)
        ', ['{'.implode(',', $ids).'}']));

        return $counts;
    }

    /**
     * Two separate ways a row can be untrustworthy: the shop had not moved to Aiku yet, or the
     * order itself came over from Aurora and was worked on paper regardless of the date.
     */
    protected function trustworthy(Builder $query, string $table): Builder
    {
        return $query->join('shops', 'shops.id', '=', $table.'.shop_id')
            ->join('delivery_notes as dn_source', 'dn_source.id', '=', $table.'.delivery_note_id')
            ->whereNotNull('shops.migrated_to_aiku_on')
            ->whereColumn($table.'.created_at', '>=', 'shops.migrated_to_aiku_on')
            ->whereNull('dn_source.source_id');
    }

    protected function pickingsToFill(): Builder
    {
        return $this->trustworthy(
            DB::table('pickings')
                ->whereNull('pickings.last_picked_at')
                ->whereIn('pickings.type', ['pick', 'magic_pick']),
            'pickings'
        );
    }

    protected function packingsToFill(): Builder
    {
        return $this->trustworthy(
            DB::table('packings')->whereNull('packings.done_at'),
            'packings'
        );
    }

    /**
     * Notes whose stored order is impossible are skipped rather than clamped: a packing that
     * started before picking finished means something else went wrong on that note, and inventing
     * a plausible order would hide it.
     */
    protected function noteLevelToFill(): Builder
    {
        return $this->trustworthy(
            DB::table('packings')
                ->join('delivery_notes', 'delivery_notes.id', '=', 'packings.delivery_note_id')
                ->whereNull('packings.queued_at')
                ->whereNotNull('delivery_notes.picked_at')
                ->whereNotNull('delivery_notes.packing_at')
                ->whereColumn('delivery_notes.packing_at', '>=', 'delivery_notes.picked_at')
                ->whereColumn('delivery_notes.picked_at', '>=', 'shops.migrated_to_aiku_on')
                /*
                 * A line packed before the note says packing started contradicts the note, so the
                 * whole note is skipped rather than part of it: filling only the lines that happen
                 * to agree would leave one note carrying two different start times.
                 */
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('packings as earlier')
                        ->whereColumn('earlier.delivery_note_id', 'packings.delivery_note_id')
                        ->whereColumn('earlier.created_at', '<', 'delivery_notes.packing_at');
                }),
            'packings'
        );
    }

    protected function countPreMigration(): int
    {
        return DB::table('packings')
            ->join('shops', 'shops.id', '=', 'packings.shop_id')
            ->join('delivery_notes as dn_source', 'dn_source.id', '=', 'packings.delivery_note_id')
            ->whereNull('packings.done_at')
            ->where(function ($query) {
                $query->whereNull('shops.migrated_to_aiku_on')
                    ->orWhereColumn('packings.created_at', '<', 'shops.migrated_to_aiku_on')
                    ->orWhereNotNull('dn_source.source_id');
            })
            ->count();
    }

    protected function countWithoutNoteSource(): int
    {
        return $this->trustworthy(
            DB::table('packings')
                ->join('delivery_notes', 'delivery_notes.id', '=', 'packings.delivery_note_id')
                ->whereNull('packings.queued_at')
                ->where(function ($query) {
                    $query->whereNull('delivery_notes.picked_at')
                        ->orWhereNull('delivery_notes.packing_at');
                }),
            'packings'
        )->count();
    }

    /**
     * Each pass re-reads the head of the still-null set, so a chunk that writes nothing ends the
     * loop rather than spinning on rows it cannot fill.
     */
    protected function fill(callable $pending, string $table, callable $write): void
    {
        while (true) {
            $ids = $pending()
                ->orderBy($table.'.id')
                ->limit(self::CHUNK_SIZE)
                ->pluck($table.'.id')
                ->all();

            if (empty($ids)) {
                return;
            }

            $write($ids);
        }
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry-run');
        $counts = $this->handle($dryRun);

        $command->table(['What', 'Rows'], [
            ['pickings.last_picked_at from created_at', number_format($counts['last_picked_at'])],
            ['packings.done_at from created_at', number_format($counts['done_at'])],
            ['packings.queued_at + packing_at from the note', number_format($counts['note_level'])],
            ['skipped, shop still on the old system', number_format($counts['skipped_pre_aiku'])],
            ['skipped, note has no timestamps', number_format($counts['skipped_no_source'])],
        ]);

        $command->info($dryRun ? 'Dry run, nothing written.' : 'Backfill complete.');

        return Command::SUCCESS;
    }
}
