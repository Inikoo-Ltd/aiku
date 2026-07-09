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

        //$dummy='{"scope":"org_stocks","results":{"org_stocks":[{"name":"Jumbo Bath Bombs - 200g","code":"JBB","state":"discontinued","id":12716},{"name":"Mixed pack","code":"JBB","state":"discontinued","id":12504},{"name":"thin wall Jbb carton","code":"Jbbpack-01thin","state":"active","id":83550},{"name":"Bespoke JBB Recipe Bath Bombs","code":"JBB-Bespoke","state":"discontinued","id":30011},{"name":"PUCKATOR - JBB Mix Rose\/Lav\/5 Her\/Tanger (BATH14)","code":"PuckJBB-Mix2","state":"active","id":27418},{"name":"PUCKATOR - JBB Mix Rose\/Lav\/Mango\/Coco (BATH13)","code":"PuckJBB-Mix1","state":"active","id":27417},{"name":"JBB Starter Pack","code":"43679","state":"discontinued","id":20603},{"name":"PUCKATOR - JBB Mix Rose\/Lav\/5 Her\/Tanger","code":"JBB-Mix2","state":"discontinuing","id":14079},{"name":"PUCKATOR - JBB Mix Rose\/Lav\/Mango\/Coco","code":"JBB-Mix1","state":"discontinued","id":13905},{"name":"JBB Gift Boxes (empty)","code":"JBB-Gift","state":"active","id":11556},{"name":"Jumbo Bath Bomb Discovery 4 of each Pack Dubai, Amethyst, Razzle, Charlotte","code":"JBB-MX4","state":"active","id":82121},{"name":"Jumbo Bath Bomb - Pink Charlotte","code":"JBB-39","state":"active","id":82120},{"name":"Jumbo Bath Bomb - Amethyst Creed","code":"JBB-37","state":"active","id":82119},{"name":"Jumbo Bath Bomb - Razzle Dazzle","code":"JBB-38","state":"active","id":82118},{"name":"Dubai Bath Bomb - Emerald Oudh Truffle","code":"JBB-36","state":"active","id":82117},{"name":"Blueberry Bath Bomb 180g","code":"JBB-33","state":"active","id":38634},{"name":"Blackberry Bath Bomb 180g","code":"JBB-34","state":"active","id":38633},{"name":"Pink Lemonade Bath Bomb 180g","code":"JBB-35","state":"active","id":38632}]}}';
        //return json_decode($dummy,true);

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
