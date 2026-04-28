<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\BatchCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateBatchCode extends OrgAction
{
    use WithActionUpdate;

    public function handle(BatchCode $batchCode, array $modelData): BatchCode
    {
        return $this->update($batchCode, $modelData);
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
