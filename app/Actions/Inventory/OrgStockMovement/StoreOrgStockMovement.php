<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 31 Aug 2024 10:38:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockMovement;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateMovements;
use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreOrgStockMovement extends OrgAction
{
    public function handle(OrgStock $orgStock, Location $location, array $modelData): OrgStockMovement
    {
        data_set($modelData, 'group_id', $location->group_id);
        data_set($modelData, 'organisation_id', $location->organisation_id);
        data_set($modelData, 'warehouse_id', $location->warehouse_id);
        data_set($modelData, 'warehouse_area_id', $location->warehouse_area_id);
        data_set($modelData, 'location_id', $location->id);

        data_set($modelData, 'date', now(), overwrite: false);


        if (!Arr::has($modelData, 'org_amount')) {
            $orgAmount = $modelData['quantity'] * $orgStock->value_in_locations;
            data_set($modelData, 'org_amount', $orgAmount);

        }

        data_set($modelData, 'grp_amount', Arr::get($modelData, 'org_amount') * GetCurrencyExchange::run($orgStock->organisation->currency, $orgStock->group->currency), overwrite: false);

        $class = OrgStockMovementClassEnum::MOVEMENT;
        if (in_array($modelData['type'], [OrgStockMovementTypeEnum::ASSOCIATE, OrgStockMovementTypeEnum::DISASSOCIATE])) {
            $class = OrgStockMovementClassEnum::HELPER;
        }

        data_set($modelData, 'class', $class);

        $flow = OrgStockMovementFlowEnum::NO_CHANGE;
        if ($modelData['quantity'] > 0) {
            $flow = OrgStockMovementFlowEnum::IN;
        } elseif ($modelData['quantity'] < 0) {
            $flow = OrgStockMovementFlowEnum::OUT;
        }

        data_set($modelData, 'flow', $flow);

        /** @var OrgStockMovement $orgStockMovement */
        $orgStockMovement = $orgStock->orgStockMovements()->create($modelData);


        if ($this->strict) {
            $locationOrgStock = LocationOrgStock::where('location_id', $location->id)->where('org_stock_id', $orgStock->id)->first();

            if ($locationOrgStock) {
                UpdateLocationOrgStock::run(
                    $locationOrgStock,
                    [
                        'quantity' => $locationOrgStock->quantity + $orgStockMovement->quantity,
                    ]
                );
            }
        }

        OrgStockHydrateMovements::dispatch($orgStock)->delay($this->hydratorsDelay);


        return $orgStockMovement;
    }

    public function rules(): array
    {
        $rules = [
            'date'         => ['sometimes', 'date'],
            'quantity'     => ['required', 'numeric'],
            'org_amount'   => ['sometimes', 'numeric'],
            'data'         => ['sometimes', 'array'],
            'type'         => ['required', Rule::enum(OrgStockMovementTypeEnum::class)],
            'is_delivered' => ['sometimes', 'boolean'],
            'is_received'  => ['sometimes', 'boolean'],
        ];
        if (!$this->strict) {
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['source_id']  = ['sometimes', 'string'];
        }

        return $rules;
    }


    public function asController(OrgStock $orgStock, Location $location, ActionRequest $request, int $hydratorsDelay = 0, bool $strict = true): OrgStockMovement
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($orgStock, $location, $this->validatedData);
    }

    public function action(OrgStock $orgStock, Location $location, array $modelData, int $hydratorsDelay = 0, bool $strict = true): OrgStockMovement
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;

        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $location, $this->validatedData);
    }


}
