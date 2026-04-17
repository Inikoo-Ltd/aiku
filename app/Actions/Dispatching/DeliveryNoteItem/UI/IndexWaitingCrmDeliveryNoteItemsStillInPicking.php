<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;

class IndexWaitingCrmDeliveryNoteItemsStillInPicking extends BaseIndexWaitingDeliveryNoteItems
{
    protected string $waitingType = 'crm';

    protected function getDeliveryNoteState(): DeliveryNoteStateEnum
    {
        return DeliveryNoteStateEnum::HANDLING;
    }

    protected function getPageTitle(): string
    {
        return __('CRM Waiting Items').' ('.__('Still picking').')';
    }

    protected function getRouteName(): string
    {
        return 'grp.org.warehouses.show.dispatching.waiting_crm_items_still_picking';
    }
}
