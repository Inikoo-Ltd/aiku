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
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class Search extends OrgAction
{
    protected const array GROUP_SCOPES = ['sysadmin', 'goods', 'supply_chain', 'trade_units', 'master_shop', 'chat'];
    protected const array ORGANISATION_SCOPES = ['accounting', 'hr'];
    protected const array SHOP_SCOPES = ['catalogue', 'prospects', 'customers', 'orders', 'reviews', 'billables', 'offers', 'marketing', 'website', 'shop_accounting'];
    protected const array WAREHOUSE_SCOPES = ['inventory', 'dispatching', 'locations'];


    public function handle(string $scope, string $query, array $options = []): array
    {
        $actions = [
            'sysadmin'     => static fn () => SearchSysAdmin::run($query),
            'goods'        => static fn () => SearchGoods::run($query),
            'supply_chain' => static fn () => SearchSupplyChain::run($query),
            'trade_units'  => static fn () => SearchTradeUnits::run($query),
            'master_shop'  => static fn () => SearchMasterShop::run($query, $options),
            'chat'         => static fn () => SearchChat::run($query, $options),
            'billables'    => static fn () => SearchBillables::run($query, $options),
            'offers'       => static fn () => SearchOffers::run($query, $options),
            'marketing'    => static fn () => SearchMarketing::run($query, $options),
            'website'      => static fn () => SearchWebsite::run($query, $options),
            'accounting'      => static fn () => SearchAccounting::run($query, $options),
            'shop_accounting' => static fn () => SearchAccounting::run($query, $options),
            'hr'              => static fn () => SearchHr::run($query, $options),
            'catalogue'    => static fn () => SearchCatalogue::run($query, $options),
            'prospects'    => static fn () => SearchProspects::run($query, $options),
            'customers'    => static fn () => SearchCustomers::run($query, $options),
            'orders'       => static fn () => SearchOrders::run($query, $options),
            'reviews'      => static fn () => SearchReviews::run($query, $options),
            'inventory'    => static fn () => SearchInventory::run($query, $options),
            'dispatching'  => static fn () => SearchDispatching::run($query, $options),
            'locations'    => static fn () => SearchLocations::run($query, $options),
        ];

        if (!isset($actions[$scope])) {
            return [];
        }

        if (mb_strlen($query) <= 2) {
            $cacheKey = 'search:'.$scope.':'.implode(':', $options).':'.mb_strtolower($query);

            return cache()->remember($cacheKey, 30, $actions[$scope]);
        }

        return $actions[$scope]();
    }

    public function rules(): array
    {
        return [
            'q'       => ['required', 'string'],
            'shop'    => ['sometimes', 'string'],
            'session' => ['sometimes', 'string', 'max:64'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $route = $request->string('route_src')->toString();
        $scope = $this->getRouteScope($route);

        $options = [];
        if (in_array($scope, self::GROUP_SCOPES, true)) {
            $this->initialisationFromGroup(app('group'), $request);
            if ($scope === 'master_shop' && $request->query('masterShop')) {
                $options = ['master_shop_id' => MasterShop::where('slug', $request->query('masterShop'))->first()?->id];
            }
            if ($scope === 'chat' && $request->query('organisation')) {
                $options = ['organisation_id' => Organisation::where('slug', $request->query('organisation'))->first()?->id];
            }
        } elseif (in_array($scope, self::ORGANISATION_SCOPES, true)) {
            $organisation = Organisation::where('slug', $request->query('organisation'))->firstOrFail();
            $this->initialisation($organisation, $request);
            $options = ['organisation_id' => $organisation->id];
        } elseif (in_array($scope, self::WAREHOUSE_SCOPES, true)) {
            $warehouse = Warehouse::where('slug', $request->query('warehouse'))->firstOrFail();
            $this->initialisationFromWarehouse($warehouse, $request);
            $options = [
                'warehouse_id'    => $warehouse->id,
                'organisation_id' => $warehouse->organisation_id,
            ];
        } elseif (in_array($scope, self::SHOP_SCOPES, true)) {
            $shop = Shop::where('slug', $request->query('shop'))->firstOrFail();
            $this->initialisationFromShop($shop, $request);
            $options = ['shop_id' => $shop->id];
        } else {
            return [];
        }

        $query   = $this->validatedData['q'];
        $results = $this->handle($scope, $query, $options);

        $ulid = (string)Str::ulid();
        StoreSearchLog::dispatchAfterResponse([
            'ulid'            => $ulid,
            'group_id'        => $this->group->id,
            'user_id'         => $request->user()?->id,
            'organisation_id' => Arr::get($options, 'organisation_id'),
            'shop_id'         => Arr::get($options, 'shop_id'),
            'warehouse_id'    => Arr::get($options, 'warehouse_id'),
            'scope'           => $scope,
            'query'           => mb_substr($query, 0, 255),
            'session_id'      => Arr::get($this->validatedData, 'session'),
            'results_count'   => collect(Arr::get($results, 'results', []))->sum(fn ($items) => count($items)),
        ]);

        $results['search_log_ulid'] = $ulid;

        return $results;
    }

    public function getRouteScope(string $route): ?string
    {
        $scopes = [
            'grp.sysadmin'                            => 'sysadmin',
            'grp.goods.'                              => 'goods',
            'grp.supply-chain.'                       => 'supply_chain',
            'grp.trade_units.'                        => 'trade_units',
            'grp.masters.'                            => 'master_shop',
            'grp.chat.'                               => 'chat',
            'grp.org.chat.'                           => 'chat',
            'grp.org.accounting.'                     => 'accounting',
            'grp.org.hr.'                             => 'hr',
            'grp.org.shops.show.dashboard'            => 'shop_accounting',
            'grp.org.shops.show.catalogue'            => 'catalogue',
            'grp.org.shops.show.crm.prospects'        => 'prospects',
            'grp.org.shops.show.crm'                  => 'customers',
            'grp.org.shops.show.ordering'             => 'orders',
            'grp.org.shops.show.reviews'              => 'reviews',
            'grp.org.shops.show.billables'            => 'billables',
            'grp.org.shops.show.discounts'            => 'offers',
            'grp.org.shops.show.marketing'            => 'marketing',
            'grp.org.shops.show.web'                  => 'website',
            'grp.org.warehouses.show.inventory.'      => 'inventory',
            'grp.org.warehouses.show.dispatching.'    => 'dispatching',
            'grp.org.warehouses.show.infrastructure.' => 'locations',
        ];

        return array_find($scopes, fn ($scope, $prefix) => str_starts_with($route, $prefix));
    }

}
