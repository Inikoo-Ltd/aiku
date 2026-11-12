<?php

/*
 * Author Andi Ferdiawan
 * Created on 11-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\OrgStock\UI\IndexOrgStocksInLocation;
use App\Http\Resources\Inventory\OrgStocksInLocationResource;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowMobileLocation
{
    use AsController;
    use WithMobileLocationsAccess;

    /**
     * The location with the SKOs of the web location page (IndexOrgStocksInLocation). The web
     * table has no picking defaults or min, max and replenishment levels, so those are added
     * from each SKO's link to the location.
     *
     * @return array<string, mixed>
     */
    public function handle(Location $location): array
    {
        $location->loadMissing(['stats', 'warehouseArea']);
        $this->useWebTableParameters([]);

        $orgStocks = $this->allWebPages(fn () => IndexOrgStocksInLocation::make()->handle($location));
        $links     = LocationOrgStock::whereIn('id', $orgStocks->pluck('location_org_stock_id'))->get()->keyBy('id');

        return array_merge($this->locationSummary($location), [
            'org_stocks' => $orgStocks
                ->map(function ($orgStock) use ($links) {
                    /** @var LocationOrgStock|null $link */
                    $link = $links->get($orgStock->location_org_stock_id);

                    return array_merge(OrgStocksInLocationResource::make($orgStock)->resolve(), [
                        'default_wholesale_picking_location'    => (bool) $link?->default_wholesale_picking_location,
                        'default_dropshipping_picking_location' => (bool) $link?->default_dropshipping_picking_location,
                        'min_stock'                             => $link?->settings['min_stock'] ?? null,
                        'max_stock'                             => $link?->settings['max_stock'] ?? null,
                        'replenishment_stock'                   => $link?->settings['replenishment_stock'] ?? null,
                        'audited_at'                            => $link?->audited_at ? Carbon::parse($link->audited_at)->toIso8601String() : null,
                    ]);
                })
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function forUser(Location $location, User $user): array
    {
        return array_merge($this->handle($location), [
            'can' => [
                'edit_stocks'    => self::canEditStocks($user, $location->warehouse),
                'edit_locations' => self::canEditLocations($user, $location->warehouse),
            ],
        ]);
    }

    public function asController(Location $location, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $location->warehouse);

        return response()->json(['data' => $this->forUser($location, $user)]);
    }
}
