<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 02:49:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;

class Search extends OrgAction
{
    protected const array SHOP_SCOPES = ['catalogue', 'prospects', 'customers'];
    protected const array WAREHOUSE_SCOPES = ['inventory', 'dispatching', 'locations'];


    public function handle(string $scope, string $query, array $options = []): array
    {
        $actions = [
            'sysadmin'  => static fn() => SearchSysAdmin::run($query),
            'catalogue' => static fn() => SearchCatalogue::run($query, $options),
            'prospects' => static fn() => SearchProspects::run($query, $options),
            'customers' => static fn() => SearchCustomers::run($query, $options),
            'inventory' => static fn() => SearchInventory::run($query, $options),
        ];

        if (!isset($actions[$scope])) {
            return [];
        }

        return $actions[$scope]();
    }

    public function rules(): array
    {
        return [
            'q'    => ['required', 'string'],
            'shop' => ['sometimes', 'string'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $route = $request->string('route_src')->toString();
        $scope = $this->getRouteScope($route);
        if ($scope === 'sysadmin') {
            $this->initialisationFromGroup(app('group'), $request);

            return $this->handle($scope, $this->validatedData['q']);
        }

        if (in_array($scope, self::WAREHOUSE_SCOPES, true)) {
            $warehouse = Warehouse::where('slug', $request->query('warehouse'))->firstOrFail();
            $this->initialisationFromWarehouse($warehouse, $request);

            return $this->handle($scope, $this->validatedData['q'], [
                'warehouse_id'    => $warehouse->id,
                'organisation_id' => $warehouse->organisation_id,
            ]);
        }

        if (in_array($scope, self::SHOP_SCOPES, true)) {
            $shop = Shop::where('slug', $request->query('shop'))->firstOrFail();
            $this->initialisationFromShop($shop, $request);

            return $this->handle($scope, $this->validatedData['q'], [
                'shop_id' => $shop->id,
            ]);
        }

        return [];
    }

    public function getRouteScope(string $route): ?string
    {
        $scopes = [
            'grp.sysadmin'                       => 'sysadmin',
            'grp.org.shops.show.catalogue'       => 'catalogue',
            'grp.org.shops.show.crm.prospects'   => 'prospects',
            'grp.org.shops.show.crm'             => 'customers',
            'grp.org.warehouses.show.inventory.' => 'inventory',
        ];

        return array_find($scopes, fn($scope, $prefix) => str_starts_with($route, $prefix));
    }

}
