<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:47:34 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Maintenance\Dispatching\RepairOrgStockMissingLocationIds;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class MoveOrgStockToOtherLocation extends OrgAction
{
    use WithActionUpdate;
    use WithLocationOrgStockActionAuthorisation;

    public function handle(LocationOrgStock $currentLocationStock, LocationOrgStock $targetLocation, array $modelData): LocationOrgStock
    {
        DB::transaction(function () use ($currentLocationStock, $targetLocation, $modelData) {    
            $quantity = Arr::pull($modelData, 'quantity');
            // Source
            AuditLocationOrgStock::make()->action($currentLocationStock, [
                'quantity'              => $currentLocationStock->quantity - $quantity,
                'stock_movement_type'   => OrgStockMovementTypeEnum::LOCATION_TRANSFER,
                'user_request'                  => request()->user()
            ]);
            // Destination
            AuditLocationOrgStock::make()->action($targetLocation, [
                'quantity'  => $targetLocation->quantity + $quantity,
                'stock_movement_type'   => OrgStockMovementTypeEnum::LOCATION_TRANSFER,
                'user_request'                  => request()->user()
            ]);
        });

        $currentLocationStock->refresh();
        $targetLocation->refresh();

        return $currentLocationStock;
    }

    public function rules(): array
    {
        return [
            'quantity' => [ 'required','numeric','gt:0'],
        ];
    }

    public function action(LocationOrgStock $currentLocationStock, LocationOrgStock $targetLocationOrgStock, array $modelData): LocationOrgStock
    {
        $this->asAction = true;
        $this->initialisation($currentLocationStock->organisation, $modelData);
        return $this->handle($currentLocationStock, $targetLocationOrgStock, $this->validatedData);
    }

    public function asController(LocationOrgStock $locationOrgStock, LocationOrgStock $targetLocationOrgStock, ActionRequest $request): void
    {
        $this->initialisation($locationOrgStock->organisation, $request);
        
        $this->handle($locationOrgStock, $targetLocationOrgStock, $this->validatedData);
    }
}
