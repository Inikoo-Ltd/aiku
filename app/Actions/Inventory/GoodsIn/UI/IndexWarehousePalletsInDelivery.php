<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Mar 2025 11:49:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\GoodsIn\UI;

use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInDelivery;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class IndexWarehousePalletsInDelivery extends OrgAction
{
    use WithFulfilmentWarehouseAuthorisation;


    public function handle(PalletDelivery $palletDelivery, $prefix = null): LengthAwarePaginator
    {

        return IndexPalletsInDelivery::run($palletDelivery, $prefix);


    }

    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletsResource::collection($pallets);
    }

    public function tableStructure(PalletDelivery $palletDelivery, $prefix = null, $modelOperations = []): Closure
    {
        return IndexPalletsInDelivery::make()->tableStructure($palletDelivery, $prefix, $modelOperations);
    }



    public function asController(Organisation $organisation, Warehouse $warehouse, PalletDelivery $palletDelivery, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($palletDelivery);
    }
}
