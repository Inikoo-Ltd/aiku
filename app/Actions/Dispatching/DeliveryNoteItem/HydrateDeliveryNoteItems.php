<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 09 Feb 2022 15:04:15 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNoteItem\Hydrators\DeliveryNoteItemHydrateSalesType;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Dispatching\DeliveryNoteItem;

class HydrateDeliveryNoteItems
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:delivery_note_items {organisations?*}';

    public function __construct()
    {
        $this->model = DeliveryNoteItem::class;
    }

    public function handle(DeliveryNoteItem $deliveryNoteItem): void
    {
        DeliveryNoteItemHydrateSalesType::run($deliveryNoteItem);
    }

}
