<?php

/*
 * Author Louis Perez
 * Created on 10-07-2026-16h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Inventory\Location;

use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Http\Resources\Inventory\LocationResource;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class PartialMoveLocationOrgStocks extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    private Location $location;

    private ?User $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(Location $location, array $modelData): Location
    {
        $targetLocation = Location::find(data_get($modelData, 'location_id'));

        DB::transaction(function () use ($location, $targetLocation, $modelData) {
            $locationOrgStocks = $location
                ->locationOrgStocks()
                ->whereIn('org_stock_id', data_get($modelData, 'org_stocks.*.org_stock_id'))
                ->get()
                ->keyBy('org_stock_id'); // Eagerload

            foreach (data_get($modelData, 'org_stocks', []) as $orgStock) {
                $locationOrgStock = $locationOrgStocks->get(data_get($orgStock, 'org_stock_id'));

                $targetLocationOrgStock = $targetLocation->locationOrgStocks()->where('org_stock_id', $locationOrgStock->org_stock_id)->first();

                if (!$targetLocationOrgStock) {
                    $movedData = $locationOrgStock->only([
                        'notes',
                        'picking_priority',
                        'type',
                    ]);
                    $targetLocationOrgStock = StoreLocationOrgStock::make()->action($locationOrgStock->orgStock, $targetLocation, $movedData, user: $this->user);
                }

                MoveOrgStockToOtherLocation::make()->action($locationOrgStock, $targetLocationOrgStock, [
                    'quantity' => data_get($orgStock, 'quantity'),
                    'reason'   => data_get($modelData, 'reason'),
                    'note'     => data_get($modelData, 'note'),
                ], $this->user);

                if (data_get($orgStock, 'remove_after_move', false)) {
                    DeleteLocationOrgStock::make()->action($locationOrgStock, $this->user);
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
            'location_id'                       => [
                'required',
                Rule::exists('locations', 'id')
                    ->where('warehouse_id', $this->location->warehouse_id)
                    ->where('status', LocationStatusEnum::OPERATIONAL->value)
                    ->where('allow_stocks', true)
                    ->whereNull('deleted_at'),
                'not_in:'.$this->location->id
            ],
            'org_stocks'                        => ['required', 'array'],
            'org_stocks.*.org_stock_id'         => [
                'required',
                Rule::exists('org_stocks', 'id')->where('organisation_id', $this->organisation->id)
            ],
            'org_stocks.*.quantity'             => ['required', 'numeric'],
            'org_stocks.*.remove_after_move'    => ['sometimes', 'nullable', 'boolean'],
            'reason'                            => [ 'required', new Enum(OrgStockMovementReasonEnum::class)],
            'note'                              => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'location_id.exists' => __('Move the stock to a working location of the same warehouse that holds stock.'),
            'location_id.not_in' => __('The source and the destination location must be different.'),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Location $location, ActionRequest $request): Location
    {
        $this->location = $location;
        $this->user     = $request->user();
        $this->initialisationFromWarehouse($location->warehouse, $request);

        return $this->handle($location, $this->validatedData);
    }
}
