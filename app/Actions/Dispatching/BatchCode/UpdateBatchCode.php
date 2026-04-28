<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentBatchCodes;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\OrgStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateBatchCode extends OrgAction
{
    use WithActionUpdate;

    public function handle(BatchCode $batchCode, array $modelData): BatchCode
    {
        $previousOrgStockId = $batchCode->org_stock_id;

        $batchCode = $this->update($batchCode, $modelData);

        if ($batchCode->wasChanged('org_stock_id')) {
            if ($previousOrgStockId) {
                OrgStockHydrateCurrentBatchCodes::dispatch(OrgStock::find($previousOrgStockId))->delay($this->hydratorsDelay);
            }
            if ($batchCode->org_stock_id) {
                OrgStockHydrateCurrentBatchCodes::dispatch(OrgStock::find($batchCode->org_stock_id))->delay($this->hydratorsDelay);
            }
        }

        return $batchCode;
    }

    public function rules(): array
    {
        return [
            'code'         => ['sometimes', 'string', 'max:255'],
            'expiry_date'  => ['sometimes', 'nullable', 'date'],
            'org_stock_id' => ['sometimes', 'nullable', 'exists:org_stocks,id'],
        ];
    }

    public function asController(BatchCode $batchCode, ActionRequest $request): BatchCode
    {
        $this->initialisation($batchCode->organisation, $request);

        return $this->handle($batchCode, $this->validatedData);
    }

    public function htmlResponse(BatchCode $batchCode, ActionRequest $request): RedirectResponse
    {
        $redirectRouteName = $request->input('redirect_route_name');
        $redirectRouteParameters = $request->input('redirect_route_parameters', []);

        if (is_string($redirectRouteName) && is_array($redirectRouteParameters)) {
            return Redirect::route($redirectRouteName, $redirectRouteParameters);
        }

        return Redirect::route('grp.org.warehouses.show.inventory.batch_codes.index', [
            'organisation' => $batchCode->organisation->slug,
            'warehouse'    => $batchCode->organisation->warehouses()->first()->slug,
        ]);
    }
}
