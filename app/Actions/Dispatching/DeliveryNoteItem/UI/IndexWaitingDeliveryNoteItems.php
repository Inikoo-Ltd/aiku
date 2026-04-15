<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;

class IndexWaitingDeliveryNoteItems extends BaseIndexWaitingDeliveryNoteItems
{
    protected function getDeliveryNoteState(): DeliveryNoteStateEnum
    {
        return DeliveryNoteStateEnum::HANDLING_BLOCKED;
    }

    protected function getPageTitle(): string
    {
        return __('Waiting items');
    }

    protected function getRouteName(): string
    {
        return 'grp.org.warehouses.show.dispatching.waiting_items';
    }
}
