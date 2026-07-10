<?php

namespace App\Actions\Inventory\Location;

use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\Location;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class MassMoveLocationOrgStocks extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Location $location, array $modelData): Location
    {
        $targetLocation = Location::find(data_get($modelData, 'location_id'));

        DB::transaction(function () use ($location, $targetLocation, $modelData) {
            foreach ($location->locationOrgStocks as $locationOrgStock) {
                $targetLocationOrgStock = $targetLocation->locationOrgStocks()->where('org_stock_id', $locationOrgStock->org_stock_id)->first();

                if (!$targetLocationOrgStock) {
                    $targetLocationOrgStock = StoreLocationOrgStock::make()->action($locationOrgStock->orgStock, $targetLocation, $locationOrgStock->only([
                        'data',
                        'settings',
                        'notes',
                        'picking_priority',
                        'type'
                    ]));
                }

                MoveOrgStockToOtherLocation::make()->action($locationOrgStock, $targetLocationOrgStock, [
                    'quantity' => $locationOrgStock->quantity
                ]);

                if (Arr::get($modelData, 'remove_after_move', false)) {
                    DeleteLocationOrgStock::make()->action($locationOrgStock);
                }
            }
        });

        $location->refresh();

        return $location;
    }

    public function rules(): array
    {
        return [
            'location_id'       => ['required', 'exists:location,id'],
            'remove_after_move' => ['sometimes', 'nullable', 'boolean']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Location $location, ActionRequest $request): Location
    {
        $this->initialisationFromWarehouse($location->warehouse, $request);

        return $this->handle($location, $this->validatedData);
    }
}
