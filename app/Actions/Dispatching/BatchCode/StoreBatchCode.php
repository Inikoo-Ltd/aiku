<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode;

use App\Actions\OrgAction;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreBatchCode extends OrgAction
{
    public function handle(Warehouse $warehouse, array $modelData): BatchCode
    {
        data_set($modelData, 'group_id', $warehouse->group_id);
        data_set($modelData, 'organisation_id', $warehouse->organisation_id);

        return BatchCode::create($modelData);
    }

    public function rules(): array
    {
        return [
            'code'         => ['required', 'string', 'max:255'],
            'expiry_date'  => ['nullable', 'date'],
            'org_stock_id' => ['nullable', 'exists:org_stocks,id'],
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): BatchCode
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function htmlResponse(BatchCode $batchCode, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.inventory.batch_codes.show', [
            'organisation' => $this->warehouse->organisation->slug,
            'warehouse'    => $this->warehouse->slug,
            'batchCode'    => $batchCode->id,
        ]);
    }
}
