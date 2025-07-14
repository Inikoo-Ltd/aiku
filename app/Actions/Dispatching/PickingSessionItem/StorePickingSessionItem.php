<?php
/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-12h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingSessionItem;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\PickingSessionItem;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePickingSessionItem extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Throwable
     */
    public function handle(PickingSession $pickingSession, array $modelData): PickingSessionItem
    {
        data_set($modelData, 'group_id', $pickingSession->group_id);
        data_set($modelData, 'organisation_id', $pickingSession->organisation_id);
        data_set($modelData, 'warehouse_id', $pickingSession->warehouse_id);

        $orgStock = OrgStock::find(Arr::get($modelData, 'org_stock_id'));

        data_set($modelData, 'location_id', $orgStock->locationOrgStocks->where('picking_priority', 1)->first()->id);
        data_set($modelData, 'org_stock_family_id', $orgStock->org_stock_family_id);
        data_set($modelData, 'stock_id', $orgStock->stock_id);
        data_set($modelData, 'stock_family_id', $orgStock->stock->stock_family_id);

        $pickingSessionItem = $pickingSession->pickingSessionItem()->create($modelData);

        return $pickingSessionItem;
    }

    public function rules(): array
    {
        $rules = [
            'org_stock_id'  => ['required', 'nullable', 'exists:org_stocks,id'],
            'quantity_required'  => ['required', 'numeric'],
        ];

        return $rules;
    }

    public function asController(PickingSession $pickingSession, ActionRequest $request)
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $this->validatedData);
    }

    public function action(PickingSession $pickingSession, array $modelData)
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $modelData);

        return $this->handle($pickingSession, $this->validatedData);
    }
}
