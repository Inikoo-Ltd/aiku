<?php

/*
 * Author Louis Perez
 * Created on 28-10-2026-12h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Inventory\Location;

use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class RemoveOrgStocksFromLocation extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    private Location $location;

    private ?User $user = null;

    /**
     * @param  array<int, int>  $orgStockIds
     *
     * @throws \Throwable
     */
    public function handle(Location $location, array $orgStockIds): int
    {
        $locationOrgStocks = $location->locationOrgStocks()->whereIn('org_stock_id', $orgStockIds)->get();

        DB::transaction(function () use ($locationOrgStocks) {
            /** @var LocationOrgStock $locationOrgStock */
            foreach ($locationOrgStocks as $locationOrgStock) {
                DeleteLocationOrgStock::make()->action($locationOrgStock, $this->user);
            }
        });

        return $locationOrgStocks->count();
    }

    public function rules(): array
    {
        return [
            'org_stock_ids'   => ['required', 'array', 'min:1'],
            'org_stock_ids.*' => [
                'integer',
                Rule::exists('location_org_stocks', 'org_stock_id')->where('location_id', $this->location->id),
            ],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Location $location, ActionRequest $request): int
    {
        $this->location = $location;
        $this->user     = $request->user();
        $this->initialisationFromWarehouse($location->warehouse, $request);

        return $this->handle($location, $this->validatedData['org_stock_ids']);
    }

    public function htmlResponse(int $removed): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => trans_choice(':count SKO removed from location|:count SKOs removed from location', $removed, ['count' => $removed]),
        ]);
    }
}
