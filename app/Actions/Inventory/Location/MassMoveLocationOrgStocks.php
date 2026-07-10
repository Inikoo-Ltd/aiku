<?php

namespace App\Actions\Inventory\Location;

use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Http\Resources\Inventory\LocationResource;
use App\Models\Inventory\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class MassMoveLocationOrgStocks extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    /**
     * @var \App\Models\Inventory\Location
     */
    private Location $location;

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
                    $movedData              = $locationOrgStock->only([
                        'notes',
                        'picking_priority',
                        'type',
                    ]);
                    $targetLocationOrgStock = StoreLocationOrgStock::make()->action($locationOrgStock->orgStock, $targetLocation, $movedData);
                }

                if($locationOrgStock->quantity>0) {
                    MoveOrgStockToOtherLocation::make()->action($locationOrgStock, $targetLocationOrgStock, [
                        'quantity' => $locationOrgStock->quantity
                    ]);
                }

                if (Arr::get($modelData, 'remove_after_move', false)) {
                    DeleteLocationOrgStock::make()->action($locationOrgStock);
                }
            }
        });

        $location->refresh();

        return $location;
    }

    public function jsonResponse(Location $location)
    {
        return LocationResource::make($location)->resolve();
    }

    public function htmlResponse(Location $location): RedirectResponse
    {
        return redirect()->back();
    }

    public function rules(): array
    {
        return [
            'location_id'       => [
                'required',
                Rule::exists('locations', 'id')->where('organisation_id', $this->organisation->id),
                'not_in:'.$this->location->id
            ],
            'remove_after_move' => ['sometimes', 'nullable', 'boolean']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Location $location, ActionRequest $request): Location
    {
        $this->location = $location;
        $this->initialisationFromWarehouse($location->warehouse, $request);

        return $this->handle($location, $this->validatedData);
    }
}
