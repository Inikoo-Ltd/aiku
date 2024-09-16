<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Sept 2024 21:29:12 Malaysia Time, Taipei, Taiwan
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait WithHydrateDeliveryNotes
{
    public function getDeliveryNotesStats(Group|Organisation|Shop|Customer $model): array
    {
        $numberDeliveryNotes = $model->deliveryNotes()->count();

        return [
            'number_delivery_notes'         => $numberDeliveryNotes,

            'last_delivery_note_created_at'    => $model->deliveryNotes()->max('created_at'),
            'last_delivery_note_dispatched_at' => $model->deliveryNotes()->max('dispatched_at'),

            'last_delivery_note_type_order_created_at'    => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->max('created_at'),
            'last_delivery_note_type_order_dispatched_at' => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->max('dispatched_at'),

            'last_delivery_note_type_replacement_created_at'    => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->max('created_at'),
            'last_delivery_note_type_replacement_dispatched_at' => $model->deliveryNotes()->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->max('dispatched_at'),


        ];
    }
}
