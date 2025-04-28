<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 04 Oct 2021 11:48:37 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Inventory\Warehouse;

use App\Actions\Inventory\Warehouse\Search\WarehouseRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWarehouses;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWarehouses;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseManagementEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Inventory\Warehouse\WarehouseStateEnum;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Models\Inventory\Warehouse;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateWarehouse extends OrgAction
{
    use WithActionUpdate;
    use WithModelAddressActions;
    use WithWarehouseManagementEditAuthorisation;

    public function handle(Warehouse $warehouse, array $modelData): Warehouse
    {
        if (Arr::has($modelData, 'address')) {
            $addressData = Arr::pull($modelData, 'address');
            $warehouse   = $this->updateModelAddress($warehouse, $addressData);
        }

        $warehouse = $this->update($warehouse, $modelData, ['data', 'settings']);


        if ($warehouse->wasChanged('state')) {
            GroupHydrateWarehouses::dispatch($warehouse->group)->delay($this->hydratorsDelay);
            OrganisationHydrateWarehouses::dispatch($warehouse->organisation)->delay($this->hydratorsDelay);
        }
        WarehouseRecordSearch::dispatch($warehouse);

        return $warehouse;
    }

    public function rules(): array
    {
        $rules = [
            'code'               => [
                'sometimes',
                'required',
                'max:16',
                'alpha_dash',
                new IUnique(
                    table: 'warehouses',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->warehouse->id
                        ],
                    ]
                ),
            ],
            'name'               => ['sometimes', 'required', 'max:250', 'string'],
            'state'              => ['sometimes', Rule::enum(WarehouseStateEnum::class)],
            'allow_stock'        => ['sometimes', 'required', 'boolean'],
            'allow_fulfilment'   => ['sometimes', 'required', 'boolean'],
            'allow_dropshipping' => ['sometimes', 'required', 'boolean'],
            'address'            => ['sometimes', 'required', new ValidAddress()],

        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }


    public function asController(Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->warehouse = $warehouse;
        $this->initialisation($warehouse->organisation, $request);


        return $this->handle(
            warehouse: $warehouse,
            modelData: $this->validatedData
        );
    }

    public function action(Warehouse $warehouse, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Warehouse
    {
        if (!$audit) {
            Warehouse::disableAuditing();
        }
        $this->asAction       = true;
        $this->warehouse      = $warehouse;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisation($warehouse->organisation, $modelData);


        return $this->handle(
            warehouse: $warehouse,
            modelData: $this->validatedData
        );
    }

    public function jsonResponse(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse);
    }
}
