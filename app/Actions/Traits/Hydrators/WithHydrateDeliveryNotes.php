<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Sept 2024 21:29:12 Malaysia Time, Taipei, Taiwan
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;

trait WithHydrateDeliveryNotes
{
    public function getDeliveryStateNotesStats(DeliveryNoteStateEnum $state, Group|Organisation|Shop $model): array
    {
        $query = DB::table('delivery_notes');

        if ($model instanceof Shop) {
            $query->where('delivery_notes.shop_id', $model->id);
        } elseif ($model instanceof Group) {
            $query->where('delivery_notes.group_id', $model->id);
        } elseif ($model instanceof Organisation) {
            $query->where('delivery_notes.organisation_id', $model->id);
        }

        $query->whereNull('delivery_notes.deleted_at')
            ->where('delivery_notes.state', $state->value)
            ->leftJoin('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
            ->distinct('delivery_note_items.id')
            ->count('delivery_note_items.id');

        return [
            'number_delivery_notes_state_'.$state->value => $model->deliveryNotes()->where('state', $state)->count(),
            'weight_delivery_notes_state_'.$state->value => $model->deliveryNotes()->where('state', $state)->sum('weight'),
            //      'number_items_delivery_notes_state_'.$state->value => $query,
        ];
    }

    public function getStoreDeliveryNotesStats(Group|Organisation|Shop|Customer $model): array
    {
        $numberDeliveryNotes = $model->deliveryNotes()->count();

        return [
            'number_delivery_notes' => $numberDeliveryNotes,
            'last_delivery_note_created_at' => $model->deliveryNotes()->max('created_at'),
            'last_delivery_note_type_order_created_at' => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->max('created_at'),
            'number_delivery_notes_type_order' => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->count(),
        ];
    }

    public function getStoreReplacementsStats(Group|Organisation|Shop|Customer $model): array
    {
        $numberDeliveryNotes = $model->deliveryNotes()->count();

        return [
            'number_delivery_notes' => $numberDeliveryNotes,
            'number_delivery_notes_type_replacement' => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->count(),
            'last_delivery_note_type_replacement_created_at' => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->max('created_at'),
        ];
    }

    public function getDispatchedDeliveryNotesStats(Group|Organisation|Shop $model): array
    {
        return [
            'last_delivery_note_dispatched_at' => $model->deliveryNotes()->max('dispatched_at'),
            'last_delivery_note_type_order_dispatched_at' => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->max('dispatched_at'),
        ];
    }

    public function getDispatchedReplacementsStats(Group|Organisation|Shop $model): array
    {
        return [
            'last_delivery_note_dispatched_at' => $model->deliveryNotes()->max('dispatched_at'),
            'last_delivery_note_type_replacement_dispatched_at' => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->max('dispatched_at'),
        ];
    }
}
