<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sept 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\GoodsIn;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Links each stock delivery to the purchase orders its lines came from. Deliveries fetched from
 * Aurora before the fetch made that link were left without it, so their goods were counted as on
 * their way twice, once on the order and once on the delivery. A fetched order line and the
 * delivery line made from it share the same Aurora transaction as source_id, which gives the link.
 */
class RepairStockDeliveryPurchaseOrderLinks
{
    use AsAction;

    public string $commandSignature = 'repair:stock_delivery_purchase_order_links {--dry-run}';

    public function handle(bool $dryRun = false): int
    {
        $links = $this->getMissingLinks();

        if ($dryRun) {
            return $links->count();
        }

        foreach ($links->chunk(1000) as $chunk) {
            DB::table('purchase_order_stock_delivery')->insertOrIgnore(
                $chunk->map(fn ($link) => (array) $link)->values()->all()
            );

            DB::table('stock_deliveries')
                ->whereIn('id', $chunk->pluck('stock_delivery_id')->unique()->values())
                ->update([
                    'number_purchase_orders' => DB::raw('(select count(*) from purchase_order_stock_delivery where purchase_order_stock_delivery.stock_delivery_id = stock_deliveries.id)'),
                ]);
        }

        return $links->count();
    }

    /**
     * @return Collection<int, object{purchase_order_id: int, stock_delivery_id: int}>
     */
    private function getMissingLinks(): Collection
    {
        return DB::table('stock_delivery_items')
            ->join('purchase_order_transactions', 'purchase_order_transactions.source_id', 'stock_delivery_items.source_id')
            ->whereNotNull('stock_delivery_items.source_id')
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('purchase_order_stock_delivery')
                    ->whereColumn('purchase_order_stock_delivery.purchase_order_id', 'purchase_order_transactions.purchase_order_id')
                    ->whereColumn('purchase_order_stock_delivery.stock_delivery_id', 'stock_delivery_items.stock_delivery_id');
            })
            ->distinct()
            ->get(['purchase_order_transactions.purchase_order_id', 'stock_delivery_items.stock_delivery_id']);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $dryRun = (bool) $command->option('dry-run');
        $count  = $this->handle($dryRun);

        $command->info(($dryRun ? 'Would link ' : 'Linked ').$count.' purchase order / stock delivery pairs');

        return 0;
    }
}
