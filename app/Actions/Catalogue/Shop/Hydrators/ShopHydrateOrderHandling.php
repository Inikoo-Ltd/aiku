<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOrderHandling implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = $this->getStats($shop);

        $shop->orderHandlingStats()->update($stats);
    }

    public function getStats(Shop $shop): array
    {

        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            return $this->getMigrationStatsHack($shop);
        }

        return [
            'number_orders_state_creating'              => $shop->orders()->where('state', OrderStateEnum::CREATING)->count(),
            'orders_state_creating_amount'              => $shop->orders()->where('state', OrderStateEnum::CREATING)->sum('net_amount'),
            'orders_state_creating_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::CREATING)->sum('org_net_amount'),
            'orders_state_creating_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::CREATING)->sum('grp_net_amount'),

            'number_orders_state_submitted'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->count(),
            'orders_state_submitted_amount'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('net_amount'),
            'orders_state_submitted_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('org_net_amount'),
            'orders_state_submitted_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('grp_net_amount'),

            'number_orders_state_submitted_paid'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::PAID)->count(),
            'orders_state_submitted_paid_amount'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::PAID)->sum('net_amount'),
            'orders_state_submitted_paid_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::PAID)->sum('org_net_amount'),
            'orders_state_submitted_paid_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::PAID)->sum('grp_net_amount'),

            'number_orders_state_submitted_not_paid'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::UNPAID)->count(),
            'orders_state_submitted_not_paid_amount'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::UNPAID)->sum('net_amount'),
            'orders_state_submitted_not_paid_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::UNPAID)->sum('org_net_amount'),
            'orders_state_submitted_not_paid_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::UNPAID)->sum('grp_net_amount'),

            'number_orders_state_in_warehouse'              => $shop->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->count(),
            'orders_state_in_warehouse_amount'              => $shop->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('net_amount'),
            'orders_state_in_warehouse_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('org_net_amount'),
            'orders_state_in_warehouse_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('grp_net_amount'),

            'number_orders_state_handling'              => $shop->orders()->where('state', OrderStateEnum::HANDLING)->count(),
            'orders_state_handling_amount'              => $shop->orders()->where('state', OrderStateEnum::HANDLING)->sum('net_amount'),
            'orders_state_handling_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::HANDLING)->sum('org_net_amount'),
            'orders_state_handling_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::HANDLING)->sum('grp_net_amount'),

            'number_orders_state_handling_blocked'              => $shop->orders()->where('state', OrderStateEnum::HANDLING_BLOCKED)->count(),
            'orders_state_handling_blocked_amount'              => $shop->orders()->where('state', OrderStateEnum::HANDLING_BLOCKED)->sum('net_amount'),
            'orders_state_handling_blocked_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::HANDLING_BLOCKED)->sum('org_net_amount'),
            'orders_state_handling_blocked_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::HANDLING_BLOCKED)->sum('grp_net_amount'),

            'number_orders_state_packed'              => $shop->orders()->where('state', OrderStateEnum::PACKED)->count(),
            'orders_state_packed_amount'              => $shop->orders()->where('state', OrderStateEnum::PACKED)->sum('net_amount'),
            'orders_state_packed_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::PACKED)->sum('org_net_amount'),
            'orders_state_packed_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::PACKED)->sum('grp_net_amount'),

            'number_orders_state_finalised'              => $shop->orders()->where('state', OrderStateEnum::FINALISED)->count(),
            'orders_state_finalised_amount'              => $shop->orders()->where('state', OrderStateEnum::FINALISED)->sum('net_amount'),
            'orders_state_finalised_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::FINALISED)->sum('org_net_amount'),
            'orders_state_finalised_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::FINALISED)->sum('grp_net_amount'),

            'number_orders_packed_today' => $shop->orders()->whereDate('packed_at', Carbon::Today())->count(),

            'orders_packed_today_amount'              => $shop->orders()->whereDate('packed_at', Carbon::Today())->sum('net_amount'),
            'orders_packed_today_amount_org_currency' => $shop->orders()->whereDate('packed_at', Carbon::Today())->sum('org_net_amount'),
            'orders_packed_today_amount_grp_currency' => $shop->orders()->whereDate('packed_at', Carbon::Today())->sum('grp_net_amount'),

            'number_orders_finalised_today'              => $shop->orders()->whereDate('finalised_at', Carbon::Today())->count(),
            'orders_finalised_today_amount'              => $shop->orders()->whereDate('finalised_at', Carbon::Today())->sum('net_amount'),
            'orders_finalised_today_amount_org_currency' => $shop->orders()->whereDate('finalised_at', Carbon::Today())->sum('org_net_amount'),
            'orders_finalised_today_amount_grp_currency' => $shop->orders()->whereDate('finalised_at', Carbon::Today())->sum('grp_net_amount'),

            'number_orders_dispatched_today' => $shop->orders()->whereDate('dispatched_at', Carbon::Today())->count(),

            'orders_dispatched_today_amount'              => $shop->orders()->whereDate('dispatched_at', Carbon::Today())->sum('net_amount'),
            'orders_dispatched_today_amount_org_currency' => $shop->orders()->whereDate('dispatched_at', Carbon::Today())->sum('org_net_amount'),
            'orders_dispatched_today_amount_grp_currency' => $shop->orders()->whereDate('dispatched_at', Carbon::Today())->sum('grp_net_amount'),

            'number_delivery_notes_state_queued' => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::QUEUED)->count(),
            'weight_delivery_notes_state_queued' => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::QUEUED)->sum('weight'),

            'number_items_delivery_notes_state_queued' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::QUEUED)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_handling' => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::HANDLING)->count(),
            'weight_delivery_notes_state_handling' => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::HANDLING)->sum('weight'),

            'number_items_delivery_notes_state_handling' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING->value)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),


            'number_delivery_notes_state_handling_blocked' => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED)->count(),


            'weight_delivery_notes_state_handling_blocked'       => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED)->sum('weight'),
            'number_items_delivery_notes_state_handling_blocked' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED->value)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_packed' => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::PACKED)->count(),
            'weight_delivery_notes_state_packed' => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::PACKED)->sum('weight'),

            'number_items_delivery_notes_state_packed' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::PACKED->value)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_finalised' => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::FINALISED)->count(),
            'weight_delivery_notes_state_finalised' => $shop->deliveryNotes()->where('state', DeliveryNoteStateEnum::FINALISED)->sum('weight'),

            'number_items_delivery_notes_state_finalised' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::FINALISED->value)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),

            'number_delivery_notes_dispatched_today' => $shop->deliveryNotes()->whereDate('dispatched_at', Carbon::Today())->count(),
            'weight_delivery_notes_dispatched_today' => $shop->deliveryNotes()->whereDate('dispatched_at', Carbon::Today())->sum('weight'),

            'number_items_delivery_notes_dispatched_today' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->whereDate('delivery_notes.dispatched_at', Carbon::Today())
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),


        ];
    }

    public function getMigrationStatsHack(Shop $shop): array
    {



        $stats = [
            'number_orders_state_creating'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::CREATING)->count(),
            'orders_state_creating_amount'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::CREATING)->sum('net_amount'),
            'orders_state_creating_amount_org_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::CREATING)->sum('org_net_amount'),
            'orders_state_creating_amount_grp_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::CREATING)->sum('grp_net_amount'),

            'number_orders_state_submitted'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->count(),
            'orders_state_submitted_amount'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->sum('net_amount'),
            'orders_state_submitted_amount_org_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->sum('org_net_amount'),
            'orders_state_submitted_amount_grp_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->sum('grp_net_amount'),

            'number_orders_state_submitted_paid'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::PAID)->count(),
            'orders_state_submitted_paid_amount'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::PAID)->sum('net_amount'),
            'orders_state_submitted_paid_amount_org_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::PAID)->sum('org_net_amount'),
            'orders_state_submitted_paid_amount_grp_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', OrderPayStatusEnum::PAID)->sum('grp_net_amount'),

            'number_orders_state_submitted_not_paid'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', '!=', OrderPayStatusEnum::PAID)->count(),
            'orders_state_submitted_not_paid_amount'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', '!=', OrderPayStatusEnum::PAID)->sum('net_amount'),
            'orders_state_submitted_not_paid_amount_org_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', '!=', OrderPayStatusEnum::PAID)->sum('org_net_amount'),
            'orders_state_submitted_not_paid_amount_grp_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::SUBMITTED)->where('pay_status', '!=', OrderPayStatusEnum::PAID)->sum('grp_net_amount'),

            'number_orders_state_in_warehouse'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::IN_WAREHOUSE)->count(),
            'orders_state_in_warehouse_amount'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('net_amount'),
            'orders_state_in_warehouse_amount_org_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('org_net_amount'),
            'orders_state_in_warehouse_amount_grp_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('grp_net_amount'),

            'number_orders_state_handling'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::HANDLING)->count(),
            'orders_state_handling_amount'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::HANDLING)->sum('net_amount'),
            'orders_state_handling_amount_org_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::HANDLING)->sum('org_net_amount'),
            'orders_state_handling_amount_grp_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::HANDLING)->sum('grp_net_amount'),

            'number_orders_state_handling_blocked'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::HANDLING_BLOCKED)->count(),
            'orders_state_handling_blocked_amount'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::HANDLING_BLOCKED)->sum('net_amount'),
            'orders_state_handling_blocked_amount_org_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::HANDLING_BLOCKED)->sum('org_net_amount'),
            'orders_state_handling_blocked_amount_grp_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::HANDLING_BLOCKED)->sum('grp_net_amount'),

            'number_orders_state_packed'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::PACKED)->count(),
            'orders_state_packed_amount'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::PACKED)->sum('net_amount'),
            'orders_state_packed_amount_org_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::PACKED)->sum('org_net_amount'),
            'orders_state_packed_amount_grp_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::PACKED)->sum('grp_net_amount'),

            'number_orders_state_finalised'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::FINALISED)->count(),
            'orders_state_finalised_amount'              => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::FINALISED)->sum('net_amount'),
            'orders_state_finalised_amount_org_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::FINALISED)->sum('org_net_amount'),
            'orders_state_finalised_amount_grp_currency' => $shop->orders()->whereNull('source_id')->where('state', OrderStateEnum::FINALISED)->sum('grp_net_amount'),

            'number_orders_packed_today' => $shop->orders()->whereNull('source_id')->whereDate('packed_at', Carbon::Today())->count(),

            'orders_packed_today_amount'              => $shop->orders()->whereNull('source_id')->whereDate('packed_at', Carbon::Today())->sum('net_amount'),
            'orders_packed_today_amount_org_currency' => $shop->orders()->whereNull('source_id')->whereDate('packed_at', Carbon::Today())->sum('org_net_amount'),
            'orders_packed_today_amount_grp_currency' => $shop->orders()->whereNull('source_id')->whereDate('packed_at', Carbon::Today())->sum('grp_net_amount'),

            'number_orders_finalised_today'              => $shop->orders()->whereNull('source_id')->whereDate('finalised_at', Carbon::Today())->count(),
            'orders_finalised_today_amount'              => $shop->orders()->whereNull('source_id')->whereDate('finalised_at', Carbon::Today())->sum('net_amount'),
            'orders_finalised_today_amount_org_currency' => $shop->orders()->whereNull('source_id')->whereDate('finalised_at', Carbon::Today())->sum('org_net_amount'),
            'orders_finalised_today_amount_grp_currency' => $shop->orders()->whereNull('source_id')->whereDate('finalised_at', Carbon::Today())->sum('grp_net_amount'),

            'number_orders_dispatched_today' => $shop->orders()->whereNull('source_id')->whereDate('dispatched_at', Carbon::Today())->count(),

            'orders_dispatched_today_amount'              => $shop->orders()->whereNull('source_id')->whereDate('dispatched_at', Carbon::Today())->sum('net_amount'),
            'orders_dispatched_today_amount_org_currency' => $shop->orders()->whereNull('source_id')->whereDate('dispatched_at', Carbon::Today())->sum('org_net_amount'),
            'orders_dispatched_today_amount_grp_currency' => $shop->orders()->whereNull('source_id')->whereDate('dispatched_at', Carbon::Today())->sum('grp_net_amount'),

            'number_delivery_notes_state_queued' => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::QUEUED)->count(),
            'weight_delivery_notes_state_queued' => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::QUEUED)->sum('weight'),

            'number_items_delivery_notes_state_queued' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->whereNull('delivery_notes.source_id')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::QUEUED)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_handling' => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::HANDLING)->count(),
            'weight_delivery_notes_state_handling' => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::HANDLING)->sum('weight'),

            'number_items_delivery_notes_state_handling' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->whereNull('delivery_notes.source_id')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING->value)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),


            'number_delivery_notes_state_handling_blocked' => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED)->count(),


            'weight_delivery_notes_state_handling_blocked'       => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED)->sum('weight'),
            'number_items_delivery_notes_state_handling_blocked' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->whereNull('delivery_notes.source_id')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED->value)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_packed' => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::PACKED)->count(),
            'weight_delivery_notes_state_packed' => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::PACKED)->sum('weight'),

            'number_items_delivery_notes_state_packed' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->whereNull('delivery_notes.source_id')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::PACKED->value)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_finalised' => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::FINALISED)->count(),
            'weight_delivery_notes_state_finalised' => $shop->deliveryNotes()->whereNull('source_id')->where('state', DeliveryNoteStateEnum::FINALISED)->sum('weight'),

            'number_items_delivery_notes_state_finalised' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->whereNull('delivery_notes.source_id')
                ->where('delivery_notes.state', DeliveryNoteStateEnum::FINALISED->value)
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),

            'number_delivery_notes_dispatched_today' => $shop->deliveryNotes()->whereNull('source_id')->whereDate('dispatched_at', Carbon::Today())->count(),
            'weight_delivery_notes_dispatched_today' => $shop->deliveryNotes()->whereNull('source_id')->whereDate('dispatched_at', Carbon::Today())->sum('weight'),

            'number_items_delivery_notes_dispatched_today' => DB::table('delivery_notes')
                ->where('delivery_notes.shop_id', $shop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->whereNull('delivery_notes.source_id')
                ->whereDate('delivery_notes.dispatched_at', Carbon::Today())
                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->distinct('delivery_note_items.id')
                ->count('delivery_note_items.id'),


        ];

        return $stats;
    }

}
