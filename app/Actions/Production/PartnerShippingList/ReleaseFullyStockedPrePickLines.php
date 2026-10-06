<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleaseFullyStockedPrePickLines
{
    use AsAction;

    public string $commandSignature = 'production:release_pre_pick {--dry-run : List what would be released and queued, then roll everything back}';
    public string $commandDescription = 'Send whole partner lines to the warehouse when the shelf holds more than they ask, and queue the shortfall of the next one';

    /** One more than the line asks must be free, so the line can be released once it is made. */
    public const SHELF_BUFFER = 1;

    /**
     * A line is released whole only when the free stock exceeds it plus every line queued ahead of it,
     * so the warehouse never walks a partial. The first line it cannot cover keeps waiting in pre-pick
     * and what it misses goes to To produce as our own restock line, landing on the shelf.
     *
     * @return array{released: int, queued: int}
     */
    public function handle(Organisation $seller): array
    {
        $releasable = $this->waitingLines($seller)
            ->filter(fn (PartnerShoppingListItem $line) => (float) $line->queued_through < (float) $line->free_stock)
            ->sortBy(fn (PartnerShoppingListItem $line) => (float) $line->queued_through)
            ->map(fn (PartnerShoppingListItem $line) => ['id' => $line->id, 'quantity' => (float) $line->quantity])
            ->values()
            ->all();

        $released = $releasable
            ? PrePickPartnerShoppingListItems::make()->action($seller, $releasable, wholeLinesOnly: true)['pre_picked']
            : 0;

        $queued = $this->waitingLines($seller)
            ->filter(fn (PartnerShoppingListItem $line) => (float) $line->shortfall > 0 && (float) $line->shortfall < (float) $line->quantity)
            ->filter(fn (PartnerShoppingListItem $line) => $this->queueShortfall($seller, $line))
            ->count();

        return ['released' => $released, 'queued' => $queued];
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

    /**
     * Tops up our own restock lines for the stock until they cover the shortfall plus the shelf
     * buffer; restock lines already queued or on the floor count, so a run never asks twice.
     */
    private function queueShortfall(Organisation $seller, PartnerShoppingListItem $line): bool
    {
        $orgStock = OrgStock::where('organisation_id', $seller->id)->where('stock_id', $line->stock_id)->first();
        if (!$orgStock || !Artefact::where('org_stock_id', $orgStock->id)->exists()) {
            return false;
        }

        $inPipeline = (float) PartnerShoppingListItem::query()
            ->where('organisation_id', $seller->id)
            ->whereNull('partner_organisation_id')
            ->whereNull('transaction_id')
            ->whereNull('pre_picked_at')
            ->where('stock_id', $line->stock_id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->sum(DB::raw('coalesce(quantity_to_produce, quantity)'));

        $missing = ceil((float) $line->shortfall) + self::SHELF_BUFFER - $inPipeline;
        if ($missing <= 0) {
            return false;
        }

        PartnerShoppingListItem::create([
            'group_id'        => $seller->group_id,
            'organisation_id' => $seller->id,
            'stock_id'        => $line->stock_id,
            'org_stock_id'    => $orgStock->id,
            'quantity'        => $missing,
            'priority'        => $line->priority,
            'needed_by'       => $line->needed_by,
            'state'           => ShoppingListItemStateEnum::OPEN,
        ]);

        return true;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        Organisation::where('is_manufacturing_hub', true)->get()->each(function (Organisation $seller) use ($command) {
            if ($command->option('dry-run')) {
                $this->dryRun($seller, $command);

                return;
            }

            $result = $this->handle($seller);
            if ($result['released'] || $result['queued']) {
                $command->info("$seller->code: {$result['released']} released, {$result['queued']} shortfalls queued");
            }
        });

        return 0;
    }

    /** A real run inside a transaction that is rolled back, so the preview is exactly what a live run would do. */
    private function dryRun(Organisation $seller, Command $command): void
    {
        $lastId    = (int) PartnerShoppingListItem::withTrashed()->max('id');
        $startedAt = now()->subSecond();

        DB::beginTransaction();
        try {
            $this->handle($seller);

            $released = PartnerShoppingListItem::query()
                ->join('stocks', 'stocks.id', 'partner_shopping_list_items.stock_id')
                ->join('organisations', 'organisations.id', 'partner_shopping_list_items.organisation_id')
                ->where('partner_shopping_list_items.partner_organisation_id', $seller->id)
                ->where('partner_shopping_list_items.pre_picked_at', '>=', $startedAt)
                ->orderBy('stocks.code')
                ->get(['stocks.code as stock', 'organisations.code as for', 'partner_shopping_list_items.quantity'])
                ->map(fn ($row) => [$row->stock, $row->for, (float) $row->quantity])
                ->all();

            $queued = PartnerShoppingListItem::query()
                ->join('stocks', 'stocks.id', 'partner_shopping_list_items.stock_id')
                ->where('partner_shopping_list_items.organisation_id', $seller->id)
                ->whereNull('partner_shopping_list_items.partner_organisation_id')
                ->where('partner_shopping_list_items.id', '>', $lastId)
                ->orderBy('stocks.code')
                ->get(['stocks.code as stock', 'partner_shopping_list_items.quantity'])
                ->map(fn ($row) => [$row->stock, (float) $row->quantity])
                ->all();
        } finally {
            DB::rollBack();
        }

        $command->info("$seller->code (dry run, nothing saved): ".count($released).' lines would be released ('.array_sum(array_column($released, 2)).' SKOs), '.count($queued).' restock lines would be queued ('.array_sum(array_column($queued, 1)).' SKOs)');
        if ($released) {
            $command->table(['Released', 'For', 'SKOs'], $released);
        }
        if ($queued) {
            $command->table(['Queued to produce', 'SKOs'], $queued);
        }
    }
}
