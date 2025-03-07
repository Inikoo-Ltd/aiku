<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Mar 2025 11:22:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\GoodsOut\UI;

use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInReturn;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentWarehouseAuthorisation;
use App\Http\Resources\Fulfilment\PalletReturnItemsUIResource;
use App\Models\Fulfilment\PalletReturn;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IndexWarehousePalletsInReturn extends OrgAction
{
    use WithFulfilmentWarehouseAuthorisation;

    public function handle(PalletReturn $palletReturn, $prefix = null): LengthAwarePaginator
    {
        return IndexPalletsInReturn::run($palletReturn, $prefix);
    }

    public function tableStructure(PalletReturn $palletReturn, $request, $prefix = null, $modelOperations = []): Closure
    {
        return IndexPalletsInReturn::make()->tableStructure($palletReturn, $request, $prefix, $modelOperations);
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($palletReturn);
    }

    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletReturnItemsUIResource::collection($pallets);
    }

}
