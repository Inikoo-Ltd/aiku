<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 04 Oct 2021 12:34:45 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Inventory\WarehouseArea;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateWarehouseAreas;
use App\Actions\Inventory\WarehouseArea\Search\WarehouseAreaRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWarehouseAreas;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWarehouseAreas;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreWarehouseArea extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Warehouse $warehouse, array $modelData): WarehouseArea
    {
        data_set($modelData, 'group_id', $warehouse->group_id);
        data_set($modelData, 'organisation_id', $warehouse->organisation_id);

        $warehouseArea = DB::transaction(function () use ($warehouse, $modelData) {
            /** @var WarehouseArea $warehouseArea */
            $warehouseArea = $warehouse->warehouseAreas()->create($modelData);
            $warehouseArea->stats()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $warehouseArea->timeSeries()->create(['frequency' => $frequency]);
            }

            return $warehouseArea;
        });
        GroupHydrateWarehouseAreas::dispatch($warehouse->group)->delay($this->hydratorsDelay);
        OrganisationHydrateWarehouseAreas::dispatch($warehouse->organisation)->delay($this->hydratorsDelay);
        WarehouseHydrateWarehouseAreas::dispatch($warehouse)->delay($this->hydratorsDelay);

        WarehouseAreaRecordSearch::dispatch($warehouseArea);

        return $warehouseArea;
    }


    public function rules(): array
    {
        $rules = [
            'code' => [
                'required',
                'max:16',
                'alpha_dash',
                new IUnique(
                    table: 'warehouse_areas',
                    extraConditions: [
                        ['column' => 'warehouse_id', 'value' => $this->warehouse->id],
                    ]
                ),
            ],
            'name' => ['required', 'max:250', 'string'],
        ];

        if (!$this->strict) {
            $rules['source_id']  = ['sometimes', 'string', 'max:64'];
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['created_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }


    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, ActionRequest $request): WarehouseArea
    {
        $this->warehouse = $warehouse;
        $this->initialisation($warehouse->organisation, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Warehouse $warehouse, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): WarehouseArea
    {
        if (!$audit) {
            WarehouseArea::disableAuditing();
        }
        $this->asAction       = true;
        $this->warehouse      = $warehouse;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisation($warehouse->organisation, $modelData);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function htmlResponse(WarehouseArea $warehouseArea): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.infrastructure.warehouse_areas.index', [$this->organisation, $warehouseArea->warehouse]);
    }

}
