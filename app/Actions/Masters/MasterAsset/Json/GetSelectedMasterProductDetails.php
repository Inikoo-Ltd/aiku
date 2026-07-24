<?php

/*
 * author Louis Perez
 * created on 19-12-2025-15h-30m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\GrpAction;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Masters\MasterAsset;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Http\Resources\Masters\MasterBulkEditProductsResource;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetSelectedMasterProductDetails extends GrpAction
{
    use WithMastersAuthorisation;

    public function handle(array $modelData): array|\JsonSerializable|\Illuminate\Contracts\Support\Arrayable
    {
        $baseCurrency   = Currency::where('code', 'EUR')->first();
        $groupCurrency  = group()->currency;
        $masterProducts = MasterAsset::whereIn('master_assets.id', $modelData['data'])
            ->leftJoin('master_product_categories as master_family', 'master_assets.master_family_id', 'master_family.id')
            ->select([
                'master_assets.*',
                'master_family.id as master_family_id',
                'master_family.name as master_family_name',
                DB::raw("'{$groupCurrency->code}' as currency_code")
            ])
            ->with('tradeUnits')
            ->orderBy('created_at')
            ->get();

        $masterShop = MasterAsset::whereIn('id', $modelData['data'])->first()->masterShop;
        $openShopsQuery = $masterShop->shops()->where('state', ShopStateEnum::OPEN);
        $openOrganisations = Organisation::whereIn('id', $openShopsQuery->pluck('organisation_id'))->get();

        $masterProducts
            ->transform(function ($masterProduct) use ($openOrganisations, $baseCurrency, $groupCurrency) {
                $grpCosts          = [];
                $avgCost           = 0;

                foreach ($openOrganisations as $organisation) {
                    $tradeUnits = [];
                    foreach ($masterProduct->tradeUnits as $tradeUnit) {
                        $tradeUnits[] = [
                            'id'       => $tradeUnit->id,
                            'model'    => $tradeUnit,
                            'quantity' => $masterProduct->units,
                        ];
                    }

                    $organisationData[$organisation->id] = GetTradeUnitDataForMasterProductCreation::make()->getOrgStockData($organisation, $tradeUnits);

                    $grpCost  = data_get($organisationData, "{$organisation->id}.grp_cost");
                    $baseCost = $grpCost > 0
                        ? formatPrice($grpCost, GetCurrencyExchange::run($groupCurrency, $baseCurrency))
                        : null;

                    $organisationData[$organisation->id]['base_cost'] = $baseCost;

                    if ($baseCost !== null) {
                        $grpCosts[$organisation->code] = $baseCost;
                    }
                }

                if (count($grpCosts)) {
                    $avgCost = array_reduce($grpCosts, fn ($carry, $item) => $carry += $item) / count($grpCosts);
                }
                
                $masterProduct->org_data     = $organisationData;
                $masterProduct->avg_org_cost = $avgCost;

                return $masterProduct;
            });

        return MasterBulkEditProductsResource::collection($masterProducts)->resolve();
    }

    public function rules(): array
    {
        return [
            'data'  => ['sometimes', 'array']
        ];

    }

    public function asController(ActionRequest $request): \Illuminate\Http\Response|array|\JsonSerializable|\Illuminate\Contracts\Support\Arrayable
    {
        $group        = group();
        $this->initialisation($group, $request);

        return $this->handle($this->validatedData);
    }

}
