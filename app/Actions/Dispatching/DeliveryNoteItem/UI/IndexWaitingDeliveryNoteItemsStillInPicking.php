<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 19:52:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\UI\Dispatch\WaitingItemsTabsEnum;
use Illuminate\Support\Arr;

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

    protected function getTabNavigation(): array
    {
        return Arr::except(WaitingItemsTabsEnum::navigation(), WaitingItemsTabsEnum::WAITING_PRODUCTION->value);
    }

    protected function getRouteName(): string
    {
        return 'grp.org.warehouses.show.dispatching.waiting_items_still_picking';
    }
}
