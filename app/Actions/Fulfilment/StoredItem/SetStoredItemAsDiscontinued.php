<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Fulfilment\StoredItem;

class SetStoredItemAsDiscontinued extends OrgAction
{
    public function handle(StoredItem $storedItem): StoredItem
    {
        return UpdateStoredItem::run($storedItem, ['state' => StoredItemStateEnum::DISCONTINUED]);
    }
}
