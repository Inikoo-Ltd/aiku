<?php

/*
 * author Arya Permana - Kirin
 * created on 10-12-2024-15h-51m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrderHandling implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_orders_state_creating'                      => $organisation->orders()->where('state', OrderStateEnum::CREATING)->count(),
            'orders_state_creating_amount_org_currency'         => $organisation->orders()->where('state', OrderStateEnum::CREATING)->sum('org_net_amount'),
            'orders_state_creating_amount_grp_currency'         => $organisation->orders()->where('state', OrderStateEnum::CREATING)->sum('grp_net_amount'),

            'number_orders_state_submitted'                     => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)->count(),
            'orders_state_submitted_amount_org_currency'        => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('org_net_amount'),
            'orders_state_submitted_amount_grp_currency'        => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('grp_net_amount'),

            'number_orders_state_submitted_paid'                => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                                                                                        ->whereColumn('payment_amount', '>=', 'total_amount')
                                                                                        ->count(),
            'orders_state_submitted_paid_amount_org_currency'   => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                                                                                        ->whereColumn('payment_amount', '>=', 'total_amount')
                                                                                        ->sum('org_net_amount'),
            'orders_state_submitted_paid_amount_grp_currency'   => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                                                                                        ->whereColumn('payment_amount', '>=', 'total_amount')
                                                                                        ->sum('grp_net_amount'),

            'number_orders_state_submitted_not_paid'                => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                                                                                            ->where('payment_amount', 0)
                                                                                            ->count(),
            'orders_state_submitted_not_paid_amount_org_currency'   => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                                                                                            ->where('payment_amount', 0)
                                                                                            ->sum('org_net_amount'),
            'orders_state_submitted_not_paid_amount_grp_currency'   => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                                                                                            ->where('payment_amount', 0)
                                                                                            ->sum('grp_net_amount'),

            'number_orders_state_in_warehouse'                 => $organisation->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->count(),
            'orders_state_in_warehouse_amount_org_currency'    => $organisation->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('org_net_amount'),
            'orders_state_in_warehouse_amount_grp_currency'    => $organisation->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('grp_net_amount'),

            'number_orders_state_handling'                      => $organisation->orders()->where('state', OrderStateEnum::HANDLING)->count(),
            'orders_state_handling_amount_org_currency'         => $organisation->orders()->where('state', OrderStateEnum::HANDLING)->sum('org_net_amount'),
            'orders_state_handling_amount_grp_currency'         => $organisation->orders()->where('state', OrderStateEnum::CREATING)->sum('grp_net_amount'),

            'number_orders_state_handling_blocked'                      => $organisation->orders()->where('state', OrderStateEnum::HANDLING_BLOCKED)->count(),
            'orders_state_handling_blocked_amount_org_currency'         => $organisation->orders()->where('state', OrderStateEnum::HANDLING_BLOCKED)->sum('org_net_amount'),
            'orders_state_handling_blocked_amount_grp_currency'         => $organisation->orders()->where('state', OrderStateEnum::HANDLING_BLOCKED)->sum('grp_net_amount'),

            'number_orders_state_packed'                        => $organisation->orders()->where('state', OrderStateEnum::PACKED)->count(),
            'orders_state_packed_amount_org_currency'           => $organisation->orders()->where('state', OrderStateEnum::PACKED)->sum('org_net_amount'),
            'orders_state_packed_amount_grp_currency'           => $organisation->orders()->where('state', OrderStateEnum::PACKED)->sum('grp_net_amount'),

            'number_orders_state_finalised'                      => $organisation->orders()->where('state', OrderStateEnum::FINALISED)->count(),
            'orders_state_finalised_amount_org_currency'         => $organisation->orders()->where('state', OrderStateEnum::FINALISED)->sum('org_net_amount'),
            'orders_state_finalised_amount_grp_currency'         => $organisation->orders()->where('state', OrderStateEnum::FINALISED)->sum('grp_net_amount'),

            'number_orders_packed_today'                => $organisation->orders()->whereDate('packed_at', Carbon::today())->count(),
            'orders_packed_today_amount_org_currency'   => $organisation->orders()->whereDate('packed_at', Carbon::today())->sum('org_net_amount'),
            'orders_packed_today_amount_grp_currency'   => $organisation->orders()->whereDate('packed_at', Carbon::today())->sum('grp_net_amount'),

            'number_orders_finalised_today'                 => $organisation->orders()->whereDate('finalised_at', Carbon::today())->count(),
            'orders_finalised_today_amount_org_currency'    => $organisation->orders()->whereDate('finalised_at', Carbon::today())->sum('org_net_amount'),
            'orders_finalised_today_amount_grp_currency'    => $organisation->orders()->whereDate('finalised_at', Carbon::today())->sum('grp_net_amount'),

            'number_orders_dispatched_today'                 => $organisation->orders()->whereDate('dispatched_at', Carbon::today())->count(),
            'orders_dispatched_today_amount_org_currency'    => $organisation->orders()->whereDate('dispatched_at', Carbon::today())->sum('org_net_amount'),
            'orders_dispatched_today_amount_grp_currency'    => $organisation->orders()->whereDate('dispatched_at', Carbon::today())->sum('grp_net_amount'),

            'number_delivery_notes_state_queued'            => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::QUEUED)->count(),
            'weight_delivery_notes_state_queued'            => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::QUEUED)->sum('weight'),
            'number_items_delivery_notes_state_queued'      => DB::table('delivery_notes')
                                                                ->where('delivery_notes.organisation_id', $organisation->id)
                                                                ->whereNull('delivery_notes.deleted_at')
                                                                ->where('delivery_notes.state', DeliveryNoteStateEnum::QUEUED->value)
                                                                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                                                                ->distinct('delivery_note_items.id')
                                                                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_handling'          => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::HANDLING)->count(),
            'weight_delivery_notes_state_handling'          => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::HANDLING)->sum('weight'),
            'number_items_delivery_notes_state_handling'    => DB::table('delivery_notes')
                                                                ->where('delivery_notes.organisation_id', $organisation->id)
                                                                ->whereNull('delivery_notes.deleted_at')
                                                                ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING->value)
                                                                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                                                                ->distinct('delivery_note_items.id')
                                                                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_handling_blocked'          => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED)->count(),
            'weight_delivery_notes_state_handling_blocked'          => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED)->sum('weight'),
            'number_items_delivery_notes_state_handling_blocked'    => DB::table('delivery_notes')
                                                                ->where('delivery_notes.organisation_id', $organisation->id)
                                                                ->whereNull('delivery_notes.deleted_at')
                                                                ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED->value)
                                                                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                                                                ->distinct('delivery_note_items.id')
                                                                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_packed'          => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::PACKED)->count(),
            'weight_delivery_notes_state_packed'          => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::PACKED)->sum('weight'),
            'number_items_delivery_notes_state_packed'    => DB::table('delivery_notes')
                                                                ->where('delivery_notes.organisation_id', $organisation->id)
                                                                ->whereNull('delivery_notes.deleted_at')
                                                                ->where('delivery_notes.state', DeliveryNoteStateEnum::PACKED->value)
                                                                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                                                                ->distinct('delivery_note_items.id')
                                                                ->count('delivery_note_items.id'),

            'number_delivery_notes_state_finalised'          => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::FINALISED)->count(),
            'weight_delivery_notes_state_finalised'          => $organisation->deliveryNotes()->where('state', DeliveryNoteStateEnum::FINALISED)->sum('weight'),
            'number_items_delivery_notes_state_finalised'    => DB::table('delivery_notes')
                                                                ->where('delivery_notes.organisation_id', $organisation->id)
                                                                ->whereNull('delivery_notes.deleted_at')
                                                                ->where('delivery_notes.state', DeliveryNoteStateEnum::FINALISED->value)
                                                                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                                                                ->distinct('delivery_note_items.id')
                                                                ->count('delivery_note_items.id'),

            'number_delivery_notes_dispatched_today'          => $organisation->deliveryNotes()->whereDate('dispatched_at', Carbon::today())->count(),
            'weight_delivery_notes_dispatched_today'          => $organisation->deliveryNotes()->whereDate('dispatched_at', Carbon::today())->sum('weight'),
            'number_items_delivery_notes_dispatched_today'    => DB::table('delivery_notes')
                                                                ->where('delivery_notes.organisation_id', $organisation->id)
                                                                ->whereNull('delivery_notes.deleted_at')
                                                                ->whereDate('delivery_notes.dispatched_at', Carbon::today())
                                                                ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                                                                ->distinct('delivery_note_items.id')
                                                                ->count('delivery_note_items.id'),


        ];

        $organisation->orderHandlingStats()->update($stats);
    }


}
