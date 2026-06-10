<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Models\Fulfilment\StoredItem;
use Lorisleiva\Actions\ActionRequest;

class MarkStoredItemAsDiscontinuing extends OrgAction
{
    use WithActionUpdate;

    public function handle(StoredItem $storedItem): StoredItem
    {
        $storedItem = UpdateStoredItem::run($storedItem, ['state' => StoredItemStateEnum::DISCONTINUING]);

        if ($storedItem->total_quantity == 0) {
            $storedItem = SetStoredItemAsDiscontinued::run($storedItem);
        }

        return $storedItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function asController(StoredItem $storedItem, ActionRequest $request): StoredItem
    {
        $this->initialisationFromFulfilment($storedItem->fulfilment, $request);

        return $this->handle($storedItem);
    }

    public function action(StoredItem $storedItem): StoredItem
    {
        return $this->handle($storedItem);
    }

    public function jsonResponse(StoredItem $storedItem): StoredItemResource
    {
        return new StoredItemResource($storedItem);
    }
}
