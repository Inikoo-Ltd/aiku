<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Sept 2024 13:22:58 Taipei Standard Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockMovement;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\LocationOrgStock\CalculateValueLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\GetLocationOrgStockQuantity;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStockMovement\Traits\WithOrgStockMovementHydrator;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Events\BroadcastStockMovement;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Support\Arr;

class UpdateOrgStockMovement extends OrgAction
{
    use WithActionUpdate;
    use WithOrgStockMovementHydrator;

    public function handle(OrgStockMovement $orgStockMovement, array $modelData): OrgStockMovement
    {
        $oldQuantity = $orgStockMovement->quantity;

        $locationOrgStock = LocationOrgStock::where('location_id', $orgStockMovement->location_id)
            ->where('org_stock_id', $orgStockMovement->org_stock_id)
            ->first();

        if (Arr::has($modelData, 'quantity')) {
            $orgAmount = $modelData['quantity'] * $orgStockMovement->orgStock->value_in_locations;
            data_set($modelData, 'org_amount', $orgAmount);
            data_set($modelData, 'grp_amount', Arr::get($modelData, 'org_amount') * GetCurrencyExchange::run($orgStockMovement->organisation->currency, $orgStockMovement->group->currency), overwrite: false);

            if (in_array($orgStockMovement->type, [
                OrgStockMovementTypeEnum::AUDIT,
                OrgStockMovementTypeEnum::ASSOCIATE,
                OrgStockMovementTypeEnum::DISASSOCIATE,

            ])) {
                $flow = OrgStockMovementFlowEnum::AUDIT;
            } elseif ($modelData['quantity'] < 0) {
                $flow = OrgStockMovementFlowEnum::OUT;
            } else {
                $flow = OrgStockMovementFlowEnum::IN;
            }
            data_set($modelData, 'flow', $flow);
        }

        $orgStockMovement->update($modelData);

        if ($oldQuantity != $orgStockMovement->quantity) {
            $currentLocationOrgStockQuantity = GetLocationOrgStockQuantity::run($orgStockMovement->orgStock, $orgStockMovement->location);
            UpdateLocationOrgStock::run(
                $locationOrgStock,
                [
                    'quantity' => $currentLocationOrgStockQuantity
                ]
            );
            CalculateValueLocationOrgStock::dispatch($locationOrgStock->id);
        }

        $this->hydrateOrgStockMovement($orgStockMovement);

        BroadcastStockMovement::dispatch($locationOrgStock);

        return $orgStockMovement;
    }


    public function rules(): array
    {
        $rules = [
            'quantity' => ['sometimes', 'numeric'],
        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
            $rules['note']            = ['sometimes', 'nullable', 'string', 'max:1024'];
        }

        return $rules;
    }

    public function action(OrgStockMovement $orgStockMovement, array $modelData, int $hydratorsDelay = 0, bool $strict = true): OrgStockMovement
    {
        $this->strict = $strict;

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($orgStockMovement->organisation, $modelData);

        return $this->handle($orgStockMovement, $this->validatedData);
    }


}
