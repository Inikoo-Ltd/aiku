<?php

/*
 * Author: Vika Aqordi
 * Created on 13-04-2026-15h-04m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Ordering\WaitingCrmItem;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class SetWaitingCrmItemAsNotPick extends OrgAction
{
    public function asController(Organisation $organisation, Shop $shop, DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);

        dd('xxxx');
    }
}
