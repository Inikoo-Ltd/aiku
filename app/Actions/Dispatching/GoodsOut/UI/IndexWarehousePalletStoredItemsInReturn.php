<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Mar 2025 11:22:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\GoodsOut\UI;

use App\Actions\Fulfilment\PalletReturnItem\UI\IndexPalletStoredItemsInReturn;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Http\Resources\Fulfilment\PalletStoredItemsInPalletReturnResource;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class IndexWarehousePalletStoredItemsInReturn extends OrgAction
{
    use WithFulfilmentWarehouseAuthorisation;

    public function handle(PalletReturn $palletReturn, $prefix = null): LengthAwarePaginator
    {
        return IndexPalletStoredItemsInReturn::run($palletReturn, $prefix);
    }

    public function tableStructure(PalletReturn $palletReturn, $request, $prefix = null, $modelOperations = []): Closure
    {
        return IndexPalletStoredItemsInReturn::make()->tableStructure($palletReturn, $request, $prefix, $modelOperations);
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($palletReturn);
    }

    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return PalletStoredItemsInPalletReturnResource::collection($storedItems);
    }

}
