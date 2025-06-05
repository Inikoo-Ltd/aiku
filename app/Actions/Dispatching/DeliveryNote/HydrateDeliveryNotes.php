<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 09 Feb 2022 15:04:15 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateDeliveryNoteItemsSalesType;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateItems;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Dispatching\DeliveryNote;

class HydrateDeliveryNotes
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:delivery_notes {organisations?*} {--s|slug=}';

    public function __construct()
    {
        $this->model = DeliveryNote::class;
    }

    public function handle(DeliveryNote $deliveryNote): void
    {
        DeliveryNoteHydrateDeliveryNoteItemsSalesType::run($deliveryNote);
        DeliveryNoteHydrateItems::run($deliveryNote);
    }

}
