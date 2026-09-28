<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\PreOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When stock comes in, the pre-orders waiting for it take it, oldest first. A pre-order moves on
 * only when everything in it can be sent: its goods are then kept for it while the balance is
 * requested, or it goes to the warehouse if it is already paid. Run when an org stock's available
 * quantity rises, and hourly over every waiting pre-order in case a rise was missed.
 */
class AllocatePreOrderStock implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(?int $orgStockId = null): string
    {
        return (string) ($orgStockId ?? 'all');
    }

    public function handle(?int $orgStockId = null): void
    {
        $preOrders = PreOrder::where('state', PreOrderStateEnum::WAITING_FOR_GOODS)
            ->when($orgStockId, fn ($query) => $query->whereIn('order_id', $this->waitingOrderIdsQuery($orgStockId)))
            ->orderBy('id')
            ->get();

        $free = [];
        foreach ($preOrders as $preOrder) {
            $needs = $this->needs($preOrder);
            if ($needs->isEmpty()) {
                continue;
            }

            foreach (OrgStock::whereIn('id', $needs->keys()->diff(array_keys($free)))->get(['id', 'quantity_available', 'is_on_demand']) as $orgStock) {
                $free[$orgStock->id] = $orgStock->is_on_demand ? INF : (float) $orgStock->quantity_available;
            }

            if ($needs->contains(fn ($quantity, $id) => ($free[$id] ?? 0) < $quantity)) {
                continue;
            }

            foreach ($needs as $id => $quantity) {
                $free[$id] -= $quantity;
            }

            ArrivePreOrder::run($preOrder);
        }
    }

    public function hasWaitingPreOrders(int $orgStockId): bool
    {
        return PreOrder::where('state', PreOrderStateEnum::WAITING_FOR_GOODS)
            ->whereIn('order_id', $this->waitingOrderIdsQuery($orgStockId))
            ->exists();
    }

    private function waitingOrderIdsQuery(int $orgStockId): \Illuminate\Database\Query\Builder
    {
        return DB::table('transactions')
            ->join('product_has_org_stocks', 'product_has_org_stocks.product_id', 'transactions.model_id')
            ->where('transactions.model_type', 'Product')
            ->where('product_has_org_stocks.org_stock_id', $orgStockId)
            ->whereNull('transactions.deleted_at')
            ->select('transactions.order_id');
    }

    /**
     * @return Collection<int, float> quantity of each org stock the whole order needs
     */
    private function needs(PreOrder $preOrder): Collection
    {
        return DB::table('transactions')
            ->join('product_has_org_stocks', 'product_has_org_stocks.product_id', 'transactions.model_id')
            ->where('transactions.order_id', $preOrder->order_id)
            ->where('transactions.model_type', 'Product')
            ->where('transactions.quantity_ordered', '>', 0)
            ->whereNull('transactions.deleted_at')
            ->groupBy('product_has_org_stocks.org_stock_id')
            ->selectRaw('product_has_org_stocks.org_stock_id, sum(transactions.quantity_ordered * product_has_org_stocks.quantity) as quantity')
            ->pluck('quantity', 'org_stock_id')
            ->map(fn ($quantity) => (float) $quantity);
    }

    public string $commandSignature = 'pre_orders:allocate';

    public function asCommand(): int
    {
        Nightwatch::dontSample();

        $this->handle();

        return 0;
    }
}
