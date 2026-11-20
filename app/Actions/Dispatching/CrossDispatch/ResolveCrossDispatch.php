<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CrossDispatch;

use App\Actions\Helpers\Country\UI\IsEuropeanUnion;
use App\Actions\Production\JobOrder\FillToOrder;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\CrossDispatch\CrossDispatchRefusalEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Ordering\Order;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class ResolveCrossDispatch
{
    use AsObject;

    /**
     * Maps every line of the order to the dispatching organisation's SKOs, or says why the order cannot be dispatched whole from there. Reads only.
     *
     * In a refusal `home_org_stock_id` is always the ordering organisation's SKO and `org_stock_id` always the dispatching organisation's.
     *
     * @param  array<int, int>|null  $fillToOrderProductIds  from fillToOrderProductIds(), when one order is resolved against several organisations
     * @return array{mapping: array<int, array<int, array{home_org_stock_id: int, org_stock_id: int, quantity: float}>>, refusals: array<int, array{reason: string, transaction_id?: int, home_org_stock_id?: int, org_stock_id?: int, stock_id?: int}>}
     */
    public function handle(Order $order, Organisation $dispatcher, ?array $fillToOrderProductIds = null): array
    {
        $refusals = $this->orderRefusals($order, $dispatcher);

        $lines = DB::table('transactions')
            ->leftJoin('product_has_org_stocks', 'product_has_org_stocks.product_id', 'transactions.model_id')
            ->leftJoin('org_stocks', fn ($join) => $join->on('org_stocks.id', 'product_has_org_stocks.org_stock_id')->whereNull('org_stocks.deleted_at'))
            ->where('transactions.order_id', $order->id)
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->whereRaw('coalesce(transactions.quantity_ordered, 0) + coalesce(transactions.quantity_bonus, 0) > 0')
            ->orderBy('transactions.id')
            ->get([
                'transactions.id as transaction_id',
                'transactions.model_id as product_id',
                'transactions.quantity_ordered',
                'transactions.quantity_bonus',
                'product_has_org_stocks.quantity as skos_per_product',
                'org_stocks.id as home_org_stock_id',
                'org_stocks.stock_id',
                'org_stocks.packed_in',
            ]);

        $skosInLine = $lines->whereNotNull('home_org_stock_id')->countBy('transaction_id');
        $lines      = $lines->reject(fn ($line) => !$line->home_org_stock_id && $skosInLine->has($line->transaction_id))->values();

        if ($lines->isEmpty()) {
            $refusals[] = ['reason' => CrossDispatchRefusalEnum::NOTHING_TO_DISPATCH->value];
        }

        $candidates = DB::table('org_stocks')
            ->where('organisation_id', $dispatcher->id)
            ->whereIn('stock_id', $lines->pluck('stock_id')->filter()->unique())
            ->whereNull('deleted_at')
            ->get(['id', 'stock_id', 'state', 'packed_in', 'quantity_available', 'sku_commercial_value'])
            ->groupBy('stock_id');

        $tradeUnits = $this->tradeUnitsOf(
            $lines->pluck('home_org_stock_id')->filter()->merge($candidates->flatten(1)->pluck('id'))->unique()
        );

        $fillProducts = $fillToOrderProductIds ?? $this->fillToOrderProductIds($order);

        $mapping = [];
        $needed  = [];
        foreach ($lines as $line) {
            $refuse = function (CrossDispatchRefusalEnum $reason, ?int $orgStockId = null) use (&$refusals, $line): void {
                $refusals[] = array_filter([
                    'reason'            => $reason->value,
                    'transaction_id'    => $line->transaction_id,
                    'home_org_stock_id' => $line->home_org_stock_id,
                    'org_stock_id'      => $orgStockId,
                    'stock_id'          => $line->stock_id,
                ]);
            };

            if (in_array($line->product_id, $fillProducts, true)) {
                $refuse(CrossDispatchRefusalEnum::FILL_TO_ORDER);
            }

            if (!$line->home_org_stock_id) {
                $refuse(CrossDispatchRefusalEnum::PRODUCT_WITHOUT_SKO);

                continue;
            }

            $matches = $candidates->get($line->stock_id, collect());
            if ($matches->count() !== 1) {
                $refuse($matches->isEmpty() ? CrossDispatchRefusalEnum::SKO_MISSING : CrossDispatchRefusalEnum::SKO_DUPLICATE);

                continue;
            }

            $sko = $matches->first();
            if (!in_array($sko->state, [OrgStockStateEnum::ACTIVE->value, OrgStockStateEnum::DISCONTINUING->value], true)) {
                $refuse(CrossDispatchRefusalEnum::SKO_INACTIVE, $sko->id);
            }

            $content     = $tradeUnits[$sko->id] ?? [];
            $homeContent = $tradeUnits[$line->home_org_stock_id] ?? [];
            if ($sko->packed_in != $line->packed_in || !$content || $content != $homeContent) {
                $refuse(CrossDispatchRefusalEnum::SKO_CONTENT_DIFFERS, $sko->id);
            }

            if ($skosInLine[$line->transaction_id] > 1 && !($sko->sku_commercial_value > 0)) {
                $refuse(CrossDispatchRefusalEnum::SKO_WITHOUT_COMMERCIAL_VALUE, $sko->id);
            }

            $quantity = DeliveryNoteItem::requiredQuantity((float) $line->skos_per_product, (float) $line->quantity_ordered + (float) $line->quantity_bonus);

            $needed[$sko->id]                 = ($needed[$sko->id] ?? 0) + $quantity;
            $mapping[$line->transaction_id][] = [
                'home_org_stock_id' => $line->home_org_stock_id,
                'org_stock_id'      => $sko->id,
                'quantity'          => $quantity,
            ];
        }

        $available = $candidates->flatten(1)->keyBy('id');
        foreach ($needed as $orgStockId => $quantity) {
            if ((float) $available[$orgStockId]->quantity_available < $quantity) {
                $refusals[] = [
                    'reason'       => CrossDispatchRefusalEnum::NOT_ENOUGH_STOCK->value,
                    'org_stock_id' => $orgStockId,
                    'stock_id'     => $available[$orgStockId]->stock_id,
                ];
            }
        }

        return [
            'mapping'  => $refusals ? [] : $mapping,
            'refusals' => $refusals,
        ];
    }

    /**
     * @return array<int, array{reason: string}>
     */
    private function orderRefusals(Order $order, Organisation $dispatcher): array
    {
        $reasons = [];

        $orgPartner = OrgPartner::where('organisation_id', $order->organisation_id)->where('partner_id', $dispatcher->id)->first();

        if ($dispatcher->id === $order->organisation_id || $dispatcher->group_id !== $order->group_id) {
            $reasons[] = CrossDispatchRefusalEnum::SAME_ORGANISATION;
        }
        if (!$orgPartner?->isCrossDispatchEnabled()) {
            $reasons[] = CrossDispatchRefusalEnum::NOT_ENABLED;
        }
        if ($order->shop->type !== ShopTypeEnum::DROPSHIPPING) {
            $reasons[] = CrossDispatchRefusalEnum::NOT_DROPSHIPPING;
        }
        if (!in_array($order->state, [OrderStateEnum::SUBMITTED, OrderStateEnum::IN_WAREHOUSE], true)) {
            $reasons[] = CrossDispatchRefusalEnum::ORDER_STATE;
        }
        if (in_array(PlatformTypeEnum::TIKTOK, [$order->platform?->type, $order->customerSalesChannel?->platform?->type], true)) {
            $reasons[] = CrossDispatchRefusalEnum::TIKTOK;
        }
        if ($order->to_be_paid_by === OrderToBePaidByEnum::CASH_ON_DELIVERY) {
            $reasons[] = CrossDispatchRefusalEnum::CASH_ON_DELIVERY;
        }
        if ($order->collection_address_id) {
            $reasons[] = CrossDispatchRefusalEnum::COLLECTION;
        }
        if ($order->preOrder()->exists()) {
            $reasons[] = CrossDispatchRefusalEnum::PRE_ORDER;
        }
        if ($order->is_shipper_locked && $order->shipper_id) {
            $reasons[] = CrossDispatchRefusalEnum::LOCKED_SHIPPER;
        }

        $notes = $order->deliveryNotes()->where('delivery_notes.state', '!=', DeliveryNoteStateEnum::CANCELLED)->get(['delivery_notes.id', 'delivery_notes.type', 'delivery_notes.state']);
        if ($notes->contains(fn ($note) => $note->type === DeliveryNoteTypeEnum::REPLACEMENT)) {
            $reasons[] = CrossDispatchRefusalEnum::REPLACEMENT;
        }
        if (
            $notes->contains(fn ($note) => !in_array($note->state, [DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED], true))
            || DB::table('delivery_note_items')->whereIn('delivery_note_id', $notes->pluck('id'))->where('quantity_picked', '>', 0)->exists()
        ) {
            $reasons[] = CrossDispatchRefusalEnum::PICKED;
        }

        if ($this->crossesCustomsBorder($order, $dispatcher) && !$orgPartner?->crossDispatchSettings()['allow_customs_crossing']) {
            $reasons[] = CrossDispatchRefusalEnum::CUSTOMS_CROSSING;
        }

        return array_map(fn (CrossDispatchRefusalEnum $reason) => ['reason' => $reason->value], $reasons);
    }

    /**
     * A parcel stays inside one customs territory only when it leaves and arrives in the UK, or leaves and arrives in the EU; anywhere else counts as a crossing.
     */
    private function crossesCustomsBorder(Order $order, Organisation $dispatcher): bool
    {
        $territory = fn (?string $countryCode) => match (true) {
            in_array($countryCode, ['GB', 'IM'], true)       => 'uk',
            $countryCode && IsEuropeanUnion::run($countryCode) => 'eu',
            default                                          => null,
        };

        $from = $territory($dispatcher->warehouses()->orderBy('id')->first()?->address?->country_code ?? $dispatcher->country?->code);
        $to   = $territory($order->deliveryAddress?->country_code);

        return $from === null || $from !== $to;
    }

    /**
     * @param  Collection<int, int>  $orgStockIds
     * @return array<int, array<int, float>>
     */
    private function tradeUnitsOf(Collection $orgStockIds): array
    {
        $content = [];
        DB::table('model_has_trade_units')
            ->where('model_type', 'OrgStock')
            ->whereIn('model_id', $orgStockIds)
            ->orderBy('trade_unit_id')
            ->get(['model_id', 'trade_unit_id', 'quantity'])
            ->each(function ($row) use (&$content) {
                $content[$row->model_id][$row->trade_unit_id] = (float) $row->quantity;
            });

        return $content;
    }

    /**
     * The products on the order that are filled to order. It depends on the order alone, so resolve it once when testing several dispatching organisations.
     *
     * @return array<int, int>
     */
    public function fillToOrderProductIds(Order $order): array
    {
        $productIds = $order->transactions()->where('model_type', 'Product')->distinct()->pluck('model_id');

        return Product::with('orgStocks')
            ->whereIn('id', $productIds)
            ->get()
            ->filter(fn (Product $product) => FillToOrder::make()->artefactFor($product) !== null)
            ->pluck('id')
            ->values()
            ->all();
    }
}
