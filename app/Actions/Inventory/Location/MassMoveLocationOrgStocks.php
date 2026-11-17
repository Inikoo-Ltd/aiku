<?php

/*
 * Author Louis Perez
 * Created on 10-07-2026-17h-09m
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

    private ?User $user = null;

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
                    $targetLocationOrgStock = StoreLocationOrgStock::make()->action($locationOrgStock->orgStock, $targetLocation, $movedData, user: $this->user);
                }

                if ($locationOrgStock->quantity > 0) {
                    MoveOrgStockToOtherLocation::make()->action($locationOrgStock, $targetLocationOrgStock, [
                        'quantity' => $locationOrgStock->quantity,
                        'reason'   => Arr::get($modelData, 'reason'),
                        'note'     => Arr::get($modelData, 'note'),
                    ], $this->user);
                }

                if (Arr::get($modelData, 'remove_after_move', false)) {
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
            'location_id'       => [
                'required',
                Rule::exists('locations', 'id')
                    ->where('warehouse_id', $this->location->warehouse_id)
                    ->where('status', LocationStatusEnum::OPERATIONAL->value)
                    ->where('allow_stocks', true)
                    ->whereNull('deleted_at'),
                'not_in:'.$this->location->id
            ],
            'remove_after_move' => ['sometimes', 'nullable', 'boolean'],
            'reason'            => ['required', Rule::in(OrgStockMovementReasonEnum::transferReason())],
            'note'              => ['sometimes', 'nullable', 'string', 'max:1024'],
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
