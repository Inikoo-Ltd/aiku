<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 */

namespace App\Actions\UI\Dispatch;

use App\Actions\Dispatching\DeliveryNote\UI\WithDeliveryNotesChannel;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Models\Dispatching\DeliveryNoteItem;

class GetDispatchHubShowcase
{
    use AsObject;
    use WithDeliveryNotesChannel;

    public function handle(Warehouse $warehouse): array
    {
        $organisation = $warehouse->organisation;
        $stats        = $organisation->stats;
        $hasB2CShops  = $organisation->shops()->where('type', ShopTypeEnum::B2C)->exists();

        $widgets = [
            ...($stats->has_fulfilment ? GetDispatchHubFulfilmentWidget::run($warehouse) : []),
            GetDispatchHubB2BWidget::run($warehouse),
            ...($stats->has_marketplace ? [GetDispatchHubExternalWidget::run($warehouse)] : []),
            ...($this->hasPartnerCustomers($organisation) ? [GetDispatchHubPartnersWidget::run($warehouse)] : []),
            ...($hasB2CShops ? [GetDispatchHubB2CWidget::run($warehouse)] : []),
            ...($stats->has_dropshipping ? [GetDispatchHubDropshippingWidget::run($warehouse)] : []),
        ];

        $dispatchedToday = $this->dispatchedTodayBySlug($warehouse);
        $shopTypeBySlug  = [
            'wholesale'            => ShopTypeEnum::B2B->value,
            self::PARTNERS_CHANNEL => self::PARTNERS_CHANNEL,
            'marketplaces'         => ShopTypeEnum::EXTERNAL->value,
            'b2c'                  => ShopTypeEnum::B2C->value,
            'dropshipping'         => ShopTypeEnum::DROPSHIPPING->value,
        ];
        foreach ($widgets as $index => $widget) {
            $widgets[$index]['dispatched_today'] = $dispatchedToday[$widget['slug']] ?? 0;
            if (isset($shopTypeBySlug[$widget['slug']])) {
                $widgets[$index]['cases']['dispatched_today']['route'] = [
                    'name'       => 'grp.org.warehouses.show.dispatching.dispatched_today.delivery-notes.shop',
                    'parameters' => [$organisation->slug, $warehouse->slug, $shopTypeBySlug[$widget['slug']]],
                ];
            }
        }

        return [
            ...$widgets,
            'waiting_items_still_picking' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->where('delivery_note_items.has_waiting_warehouse', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING)
                    ->count(),
                'route' => [
                    'name' => 'grp.org.warehouses.show.dispatching.waiting_items_still_picking',
                    'parameters' => request()->route()->originalParameters()
                ],
            ],
            'waiting_items' => [
                ...DeliveryNoteItem::waitingCounts(
                    $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->where('delivery_note_items.has_waiting_warehouse', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
                ),
                'route' => [
                    'name' => 'grp.org.warehouses.show.dispatching.waiting_items',
                    'parameters' => request()->route()->originalParameters()
                ],
            ],
            'waiting_crm_items_still_picking' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->where('delivery_note_items.has_waiting_crm', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING)
                    ->count(),
                'route' => [
                    'name' => 'grp.org.warehouses.show.dispatching.waiting_crm_items_still_picking',
                    'parameters' => request()->route()->originalParameters()
                ],
            ],
            'waiting_crm_items' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->where('delivery_note_items.has_waiting_crm', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
                    ->count(),
                'route' => [
                    'name' => 'grp.org.warehouses.show.dispatching.waiting_crm_items',
                    'parameters' => request()->route()->originalParameters()
                ],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function dispatchedTodayBySlug(Warehouse $warehouse): array
    {
        $timezone   = $warehouse->organisation->timezone?->name ?? 'UTC';
        $startOfDay = Carbon::now($timezone)->startOfDay()->utc();

        $deliveryNotes = DB::table('delivery_notes')
            ->join('shops', 'delivery_notes.shop_id', '=', 'shops.id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('delivery_notes.deleted_at')
            ->where('delivery_notes.state', DeliveryNoteStateEnum::DISPATCHED)
            ->where('delivery_notes.dispatched_at', '>=', $startOfDay)
            ->selectRaw(Shop::dispatchingChannelSql().' as channel')
            ->selectRaw('exists (select 1 from org_partners where org_partners.partner_id = ? and org_partners.customer_id = delivery_notes.customer_id) as is_partner', [$warehouse->organisation_id])
            ->selectRaw('count(*) as dispatched')
            ->groupBy('channel', 'is_partner')
            ->get();

        $counts = [];
        foreach ($deliveryNotes as $row) {
            $slug = match ($row->channel) {
                ShopTypeEnum::B2B->value          => $row->is_partner ? self::PARTNERS_CHANNEL : 'wholesale',
                ShopTypeEnum::EXTERNAL->value     => 'marketplaces',
                ShopTypeEnum::B2C->value          => 'b2c',
                ShopTypeEnum::DROPSHIPPING->value => 'dropshipping',
                default                           => null,
            };
            if ($slug) {
                $counts[$slug] = ($counts[$slug] ?? 0) + (int) $row->dispatched;
            }
        }

        $palletReturns = PalletReturn::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('dispatched_at', '>=', $startOfDay)
            ->selectRaw('type, count(*) as dispatched')
            ->groupBy('type')
            ->pluck('dispatched', 'type');

        $counts['fulfilment_pallet'] = (int) ($palletReturns[PalletReturnTypeEnum::PALLET->value] ?? 0);
        $counts['fulfilment_ds']     = (int) ($palletReturns[PalletReturnTypeEnum::STORED_ITEM->value] ?? 0);

        return $counts;
    }
}
