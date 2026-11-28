<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CrossDispatch;

use App\Enums\Dispatching\CrossDispatch\CrossDispatchRefusalEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class RouteOrderToOtherWarehouse
{
    use AsObject;

    /**
     * INI-485 D1/D4. Where an order of a shop that sells other warehouses' stock goes when it is sent to the warehouse.
     * Null: home, as today. Otherwise the warehouse to raise the note in, or the reason to hold it for staff.
     * Only an order with a line the home warehouse cannot cover, of a product that sells stock from elsewhere, is
     * routed; an order already held is checked again while the shop still counts another warehouse, and goes home as
     * today once it counts none. TikTok, pre-order, collection and banned-delivery orders always stay at home. Reads only.
     *
     * @return array{dispatcher: Organisation}|array{hold: string}|null
     */
    public function handle(Order $order): ?array
    {
        if ($this->staysHome($order)) {
            return null;
        }

        $alreadyHeld = Arr::has($order->data ?? [], SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD);
        if (!$order->shop->stockFromOtherWarehouses() || !$this->hasLineHomeCannotCover($order, onlySoldElsewhere: !$alreadyHeld)) {
            return null;
        }

        $options = $this->warehouseOptions($order);
        foreach ($options as $option) {
            if (!$option['can_ship']) {
                continue;
            }

            return $order->shop->stockElsewhereSetting('only_elsewhere') === 'send'
                ? ['dispatcher' => $option['organisation']]
                : ['hold' => __('only :warehouse can ship the whole order, send it there or home', ['warehouse' => $option['organisation']->name])];
        }

        if ($order->shop->stockElsewhereSetting('mixed') !== 'hold') {
            return null;
        }

        $orderRefusal = collect($options)->pluck('order_refusal')->filter()->first();

        return ['hold' => $orderRefusal
            ? __('another warehouse has the stock but cannot take this order: :reason', ['reason' => $orderRefusal])
            : __('no single warehouse can ship the whole order, some lines are only in another warehouse')];
    }

    /**
     * Each warehouse of the shop's list, in list order, and whether it can ship the whole order now after its margin,
     * with what stops it otherwise; for the routing and for the held order's buttons.
     *
     * @return list<array{organisation: Organisation, can_ship: bool, shortfall: string|null, order_refusal: string|null}>
     */
    public function warehouseOptions(Order $order): array
    {
        $fillToOrderProductIds = ResolveCrossDispatch::make()->fillToOrderProductIds($order);

        $options = [];
        foreach ($order->shop->stockFromOtherWarehouses() as $entry) {
            $organisation = Organisation::find($entry['organisation_id']);
            if (!$organisation) {
                continue;
            }

            $result   = ResolveCrossDispatch::run($order, $organisation, $fillToOrderProductIds);
            $refusals = array_values(array_unique(array_column($result['refusals'], 'reason')));
            if (!$refusals && !$this->coversAfterMargin($result['mapping'], $entry['safety_margin'])) {
                $refusals = [CrossDispatchRefusalEnum::NOT_ENOUGH_STOCK->value];
            }

            $options[] = [
                'organisation'  => $organisation,
                'can_ship'      => !$refusals,
                'shortfall'     => $refusals ? implode(', ', array_map(fn (string $reason) => self::refusalText($reason), $refusals)) : null,
                'order_refusal' => collect($refusals)->intersect(self::ORDER_REFUSALS)->map(fn (string $reason) => self::refusalText($reason))->first(),
            ];
        }

        return $options;
    }

    /**
     * The order locked for update, when it is still held for another warehouse: submitted, with the hold and no live
     * delivery note. Call inside a transaction, so two sends of one held order never both raise a note.
     */
    public static function lockStillHeld(Order $order): ?Order
    {
        if (!Arr::has($order->data ?? [], SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD)) {
            return null;
        }

        $lockedOrder = Order::lockForUpdate()->find($order->id);

        return $lockedOrder
            && $lockedOrder->state === OrderStateEnum::SUBMITTED
            && Arr::has($lockedOrder->data ?? [], SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD)
            && !$lockedOrder->deliveryNotes()->where('delivery_notes.state', '!=', DeliveryNoteStateEnum::CANCELLED)->exists()
            ? $lockedOrder : null;
    }

    /** Refusals that come from the order, not from the stock: no other warehouse can take such an order */
    private const array ORDER_REFUSALS = ['cash_on_delivery', 'locked_shipper', 'customs_crossing'];

    public static function refusalText(string $reason): string
    {
        return match ($reason) {
            'cash_on_delivery' => __('it is paid on delivery'),
            'locked_shipper'   => __('its shipper is locked'),
            'customs_crossing' => __('it would cross a customs border'),
            'not_enough_stock' => __('not enough stock there'),
            'not_enabled'      => __('cross dispatch is switched off between the two companies'),
            default            => str_replace('_', ' ', $reason),
        };
    }

    private function staysHome(Order $order): bool
    {
        return in_array(PlatformTypeEnum::TIKTOK, [$order->platform?->type, $order->customerSalesChannel?->platform?->type], true)
            || $order->collection_address_id
            || Arr::get($order->data, 'banned_delivery')
            || $order->preOrder()->exists();
    }

    /**
     * A line the home SKOs cannot cover; on-demand products are never short. A new order is routed only for a product
     * the shop sells from another warehouse too, while an order already held is checked again whatever that figure says.
     */
    private function hasLineHomeCannotCover(Order $order, bool $onlySoldElsewhere): bool
    {
        return DB::table('transactions')
            ->join('products', 'products.id', 'transactions.model_id')
            ->join('product_has_org_stocks', 'product_has_org_stocks.product_id', 'transactions.model_id')
            ->join('org_stocks', 'org_stocks.id', 'product_has_org_stocks.org_stock_id')
            ->where('transactions.order_id', $order->id)
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->whereNull('org_stocks.deleted_at')
            ->where('products.is_on_demand', false)
            ->when($onlySoldElsewhere, fn ($query) => $query->where('products.available_quantity_elsewhere', '>', 0))
            ->whereRaw('org_stocks.quantity_available < coalesce(product_has_org_stocks.quantity, 1) * (coalesce(transactions.quantity_ordered, 0) + coalesce(transactions.quantity_bonus, 0))')
            ->exists();
    }

    /**
     * @param  array<int, array<int, array{home_org_stock_id: int, org_stock_id: int, quantity: float}>>  $mapping
     */
    private function coversAfterMargin(array $mapping, int $safetyMargin): bool
    {
        if ($safetyMargin <= 0) {
            return true;
        }

        $needed = collect($mapping)->flatten(1)->groupBy('org_stock_id')->map(fn ($lines) => $lines->sum('quantity'));
        $available = DB::table('org_stocks')->whereIn('id', $needed->keys())->pluck('quantity_available', 'id');

        return $needed->every(fn (float $quantity, int $orgStockId) => (float) $available->get($orgStockId) - $safetyMargin >= $quantity);
    }
}
