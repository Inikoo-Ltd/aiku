<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleaseFullyStockedPrePickLines
{
    use AsAction;

    public string $commandSignature = 'production:release_pre_pick {--dry-run : List what would be released, then roll everything back}';
    public string $commandDescription = 'Send whole partner lines to the warehouse when the shelf holds more than they ask';

    /**
     * A line is released whole only when the free stock exceeds it plus every line queued ahead of it,
     * so the warehouse never walks a partial. A line it cannot cover keeps waiting in pre-pick for the
     * production manager to decide; nothing is raised to To produce for it.
     */
    public function handle(Organisation $seller): int
    {
        $releasable = $this->waitingLines($seller)
            ->filter(fn (PartnerShoppingListItem $line) => (float) $line->queued_through < (float) $line->free_stock)
            ->sortBy(fn (PartnerShoppingListItem $line) => (float) $line->queued_through)
            ->map(fn (PartnerShoppingListItem $line) => ['id' => $line->id, 'quantity' => (float) $line->quantity])
            ->values()
            ->all();

        return $releasable
            ? PrePickPartnerShoppingListItems::make()->action($seller, $releasable, wholeLinesOnly: true)['pre_picked']
            : 0;
    }

    /** @return Collection<int, PartnerShoppingListItem> */
    private function waitingLines(Organisation $seller): Collection
    {
        return PartnerShoppingListItem::query()
            ->where('partner_organisation_id', $seller->id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereNull('pre_picked_at')
            ->whereNull('job_order_id')
            ->whereNull('preparing_at')
            ->select(['id', 'partner_organisation_id', 'stock_id', 'quantity', 'priority', 'needed_by', 'created_at'])
            ->selectRaw(PartnerShoppingListItem::queuedThroughSql().' as queued_through')
            ->selectRaw(PartnerShoppingListItem::freeStockSql().' as free_stock')
            ->selectRaw(PartnerShoppingListItem::shortfallSql().' as shortfall')
            ->get();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        Organisation::where('is_manufacturing_hub', true)->get()->each(function (Organisation $seller) use ($command) {
            if ($command->option('dry-run')) {
                $this->dryRun($seller, $command);

                return;
            }

            if ($released = $this->handle($seller)) {
                $command->info("$seller->code: $released released");
            }
        });

        return 0;
    }

    /** A real run inside a transaction that is rolled back, so the preview is exactly what a live run would do. */
    private function dryRun(Organisation $seller, Command $command): void
    {
        $waitingIds = $this->waitingLines($seller)->pluck('id')->all();

        DB::beginTransaction();
        try {
            $this->handle($seller);

            $released = PartnerShoppingListItem::query()
                ->join('stocks', 'stocks.id', 'partner_shopping_list_items.stock_id')
                ->join('organisations', 'organisations.id', 'partner_shopping_list_items.organisation_id')
                ->where('partner_shopping_list_items.partner_organisation_id', $seller->id)
                ->whereIn('partner_shopping_list_items.id', $waitingIds)
                ->whereNotNull('partner_shopping_list_items.pre_picked_at')
                ->orderBy('stocks.code')
                ->get(['stocks.code as stock', 'organisations.code as for', 'partner_shopping_list_items.quantity'])
                ->map(fn ($row) => [$row->stock, $row->for, (float) $row->quantity])
                ->all();

        } finally {
            DB::rollBack();
        }

        $command->info("$seller->code (dry run, nothing saved): ".count($released).' lines would be released ('.array_sum(array_column($released, 2)).' SKOs)');
        if ($released) {
            $command->table(['Released', 'For', 'SKOs'], $released);
        }
    }
}
