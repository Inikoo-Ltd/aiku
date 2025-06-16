<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 Aug 2024 19:44:08 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Http\Resources\Inventory\LocationOrgStockResource;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateLocationOrgStock extends OrgAction
{
    use WithActionUpdate;

    private LocationOrgStock $locationOrgStock;


    public function handle(LocationOrgStock $locationOrgStock, array $modelData): LocationOrgStock
    {
        $locationOrgStock = $this->update($locationOrgStock, $modelData, ['data']);

        if ($locationOrgStock->wasChanged('quantity')) {
            OrgStockHydrateQuantityInLocations::dispatch($locationOrgStock->orgStock);
        }

        return $locationOrgStock;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("locations.{$this->warehouse->id}.view");
    }

    public function prepareForValidation(): void
    {
        if ($this->has('type') && $this->get('type') == LocationStockTypeEnum::PICKING->value) {
            foreach (
                LocationOrgStock::where('type', LocationStockTypeEnum::PICKING->value)->where('org_stock_id', $this->locationOrgStock->org_stock_id)
                    ->where('id', '!=', $this->locationOrgStock->id)->get() as $locationOrgStock
            ) {
                UpdateLocationOrgStock::make()->action($locationOrgStock, ['type' => LocationStockTypeEnum::STORING->value]);
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'quantity'         => ['sometimes', 'numeric'],
            'data'             => ['sometimes', 'array'],
            'settings'         => ['sometimes', 'array'],
            'notes'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'picking_priority' => ['sometimes', 'integer'],
            'type'             => ['sometimes', Rule::enum(LocationStockTypeEnum::class)],
        ];

        if (!$this->strict) {
            $rules['audited_at']      = ['date'];
            $rules['last_fetched_at'] = ['sometimes', 'date'];

            $rules['source_stock_id']    = ['sometimes', 'string', 'max:255'];
            $rules['source_location_id'] = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    public function action(LocationOrgStock $locationOrgStock, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): LocationOrgStock
    {
        if (!$audit) {
            LocationOrgStock::disableAuditing();
        }
        $this->hydratorsDelay   = $hydratorsDelay;
        $this->strict           = $strict;
        $this->asAction         = true;
        $this->locationOrgStock = $locationOrgStock;
        $this->initialisation($locationOrgStock->organisation, $modelData);

        return $this->handle($locationOrgStock, $this->validatedData);
    }

    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): LocationOrgStock
    {
        $this->asAction         = true;
        $this->locationOrgStock = $locationOrgStock;
        $this->initialisation($locationOrgStock->organisation, $request);

        return $this->handle($locationOrgStock, $this->validatedData);
    }

    public function jsonResponse(LocationOrgStock $locationOrgStock): LocationOrgStockResource
    {
        return new LocationOrgStockResource($locationOrgStock);
    }
}
