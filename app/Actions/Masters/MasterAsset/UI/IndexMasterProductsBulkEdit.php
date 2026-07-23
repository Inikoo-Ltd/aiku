<?php

/*
 * Author: Vika Aqordi
 * Created on 22-12-2025-16h-04m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterFamilySubNavigation;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexMasterProductsBulkEdit extends GrpAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;
    use WithMasterFamilySubNavigation;
    use WithMastersAuthorisation;

    public function handle(MasterShop $parent, $prefix = null): Group|MasterShop|MasterProductCategory
    {
        return $parent;
    }

    public function jsonResponse(MasterShop $parent): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($parent);
    }

    public function htmlResponse(MasterShop $parent, ActionRequest $request): Response
    {
        $title = __('Bulk edit Master Products');
    
        $shopCurrencies = Shop::where('master_shop_id', $parent->id)
            ->select('currency_id')
            ->distinct()
            ->get();

        $baseEuro   = Currency::where('code', 'EUR')->first();
        $currencies = Currency::whereIn('id', $shopCurrencies)->get();
        $currenciesRate   = $currencies->mapWithKeys(function ($currency) use ($baseEuro) {
            $ratioEuro  = GetCurrencyExchange::run($baseEuro, $currency);

            return [
                $currency->code => [
                    'ratio_eur'     => $ratioEuro,
                    'currency'      => $currency->code,
                    'currency_symbol'  => $currency->symbol,
                    'currency_id'      => $currency->id,
                ]
            ];
        });

        return Inertia::render(
            'Masters/MasterProductsBulkEdit',
            [
                'title'                 => $title,
                'pageHead'              => [
                    'model'         => __('Master Products'),
                    'title'         => __('Bulk Edit'),
                ],
                'currencies'        => $currenciesRate,
            ]
        );
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): Group|MasterShop|MasterProductCategory
    {
        $this->initialisation(group(), $request);

        return $this->handle($masterShop);
    }

}
