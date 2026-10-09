<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentBatchCodes;
use App\Actions\OrgAction;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreBatchCode extends OrgAction
{
    public function handle(Warehouse|Organisation $parent, array $modelData): BatchCode
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent instanceof Organisation ? $parent->id : $parent->organisation_id);

        $batchCode = BatchCode::firstOrCreate(
            Arr::only($modelData, ['organisation_id', 'org_stock_id', 'code', 'expiry_date']) + ['expiry_date' => null],
            $modelData
        );

        OrgStockHydrateCurrentBatchCodes::run(OrgStock::find($batchCode->org_stock_id));

        return $batchCode;
    }

    public function rules(): array
    {
        return [
            'code'         => ['required', 'string', 'max:255'],
            'expiry_date'  => ['nullable', 'date'],
            'org_stock_id' => ['required', 'exists:org_stocks,id'],
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): BatchCode
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function action(Warehouse $warehouse, array $modelData): BatchCode
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($warehouse, $modelData);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function inOrganisation(Organisation $organisation, array $modelData): BatchCode
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(BatchCode $batchCode, ActionRequest $request): RedirectResponse
    {
        $redirectRouteName = $request->input('redirect_route_name');
        $redirectRouteParameters = $request->input('redirect_route_parameters', []);

        if (is_string($redirectRouteName) && is_array($redirectRouteParameters)) {
            return Redirect::route($redirectRouteName, $redirectRouteParameters);
        }

        return Redirect::route('grp.org.warehouses.show.inventory.batch_codes.index', [
            'organisation' => $this->warehouse->organisation->slug,
            'warehouse'    => $this->warehouse->slug,
        ]);
    }
}
