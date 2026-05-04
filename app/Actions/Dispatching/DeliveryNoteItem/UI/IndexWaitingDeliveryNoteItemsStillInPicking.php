<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 19:52:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;

class IndexWaitingDeliveryNoteItemsStillInPicking extends BaseIndexWaitingDeliveryNoteItems
{
    protected function getDeliveryNoteState(): DeliveryNoteStateEnum
    {
        return DeliveryNoteStateEnum::HANDLING;
    }

    protected function getPageTitle(): string
    {
        return __('Waiting Items').' ('.__('Still picking').')';
    }

    protected function getRouteName(): string
    {
        return 'grp.org.warehouses.show.dispatching.waiting_items_still_picking';
    }
}
