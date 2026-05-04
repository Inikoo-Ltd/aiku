<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\UI\Dispatch\WaitingItemsTabsEnum;
use Illuminate\Support\Arr;

class IndexWaitingCrmDeliveryNoteItems extends BaseIndexWaitingDeliveryNoteItems
{
    protected string $waitingType = 'crm';

    protected bool $readOnly = true;

    protected function getDeliveryNoteState(): DeliveryNoteStateEnum
    {
        return DeliveryNoteStateEnum::HANDLING_BLOCKED;
    }

    protected function getPageTitle(): string
    {
        return __('CRM Waiting items');
    }

    protected function getRouteName(): string
    {
        return 'grp.org.warehouses.show.dispatching.waiting_crm_items';
    }

    protected function getTabNavigation(): array
    {
        return Arr::only(WaitingItemsTabsEnum::navigation(), WaitingItemsTabsEnum::ITEMIZED->value);
    }
}
