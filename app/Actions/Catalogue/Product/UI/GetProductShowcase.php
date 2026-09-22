<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:53:47 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Actions\Traits\HasBucketImages;
use App\Actions\Traits\WithSearchInWebsiteAvailabilityChecklist;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Actions\Goods\TradeUnit\GetLabelInfoLanguages;
use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Catalogue\ProductResource;
use App\Http\Resources\Catalogue\TagsResource;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Inventory\OrgStock\Json\GetOrgStocksInProduct;
use App\Actions\Traits\HasBucketAttachment;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Inventory\OrgStocksResource;
use Illuminate\Support\Facades\DB;

class GetProductShowcase
{
    use AsObject;
    use HasBucketImages;
    use HasBucketAttachment;
    use WithSearchInWebsiteAvailabilityChecklist;

    public function handle(Product $product): array
    {
        $webpageUrl = null;
        if ($product->webpage && $product->webpage->state == WebpageStateEnum::LIVE) {
            $webpageUrl = $product->webpage->canonical_url;
        }


        $countryOrigins = [];
        // $countryOrigin  = null;
        $countries      = array_filter(array_map('trim', explode(',', $product->country_of_origin ?? '')));
        foreach ($countries as $country) {
            $countryOrigins[] = NaturalLanguage::make()->country($country);
            // $countryOrigin    = NaturalLanguage::make()->country($country);
        }


        $properties = [
            // 'country_of_origin'  => $countryOrigin,
            'countries_of_origin' => $countryOrigins,
            'ingredients'        => $product->marketing_ingredients ? explode(', ', $product->marketing_ingredients) : [],
            'tariff_code'        => $product->tariff_code,
            'duty_rate'          => $product->duty_rate,
            'hts_us'             => $product->hts_us,
        ];

        $gpsr = [
            'manufacturer'               => $product->gpsr_manufacturer,
            'eu_responsible'             => $product->gpsr_eu_responsible,
            'warnings'                   => $product->gpsr_warnings,
            'how_to_use'                 => $product->gpsr_manual,
            'gpsr_class_category_danger' => $product->gpsr_class_category_danger,
            'product_languages'          => $product->gpsr_class_languages,
            'acute_toxicity'             => $product->pictogram_toxic,
            'corrosive'                  => $product->pictogram_corrosive,
            'explosive'                  => $product->pictogram_explosive,
            'flammable'                  => $product->pictogram_flammable,
            'gas_under_pressure'         => $product->pictogram_gas,
            'hazard_environment'         => $product->pictogram_environment,
            'health_hazard'              => $product->pictogram_health,
            'oxidising'                  => $product->pictogram_oxidising,
        ];


        $parentLink = null;
        if ($product->not_for_sale_from_master || $product->not_for_sale_from_trade_unit) {
            if ($product->not_for_sale_from_master) {
                $parentLink = [
                    'url'    => "grp.masters.master_shops.show.master_products.edit",
                    'params' => [
                        'masterShop'    => $product->masterProduct->masterShop->slug,
                        'masterProduct' => $product->masterProduct->slug,
                    ]
                ];
            } else {
                $parentLink = [
                    'url'    => "grp.trade_units.units.edit",
                    'params' => [
                        'tradeUnit' => $product->tradeUnits->where('is_for_sale', false)->first()->slug,
                    ]
                ];
            }
        }


        return [
            'product'                      => ProductResource::make($product),
            'is_external'                  => $product->shop->type == ShopTypeEnum::EXTERNAL,
            'properties'                   => $properties,
            'gpsr'                         => $gpsr,
            'label_info'                   => [
                ...TradeUnitLabelPresenceEnum::presenceFromLabelInfo($product->label_info),
                'label_info_approved' => ['show' => data_get($product->label_info, 'label_info_approved', false) === true],
                'markets'   => TradeUnitMarketEnum::marketsFromLabelInfo($product->label_info),
                'languages' => GetLabelInfoLanguages::run($product->label_info),
                'best_before' => TradeUnitBestBeforeEnum::bestBeforeFromLabelInfo($product->label_info),
                'packaging_material_codes' => TradeUnitPackagingMaterialEnum::packagingMaterialCodesFromLabelInfo($product->label_info),
            ],
            'parts'                        => // todo: delete this asap use org_stocks
                OrgStocksResource::collection(GetOrgStocksInProduct::run($product))->resolve(),
            'org_stocks'                   => OrgStocksResource::collection(GetOrgStocksInProduct::run($product))->resolve(),
            'stock_locations'              => $this->getStockLocations($product),
            'incoming_stock'               => GetProductIncomingStock::run($product),
            'stats'                        => $product->stats,
            'images'                       => $this->getImagesData($product, true),
            'brand'                        => $product->brand(),
            'tags'                         => TagsResource::collection($product->tags)->toArray(request()),
            'main_image'                   => $product->imageSources(),
            'attachment_box'               => $this->getAttachmentData($product),
            'webpage_url'                  => $webpageUrl,
            'availability_status'          => [
                'is_for_sale'        => $product->is_for_sale,
                'from_master'        => $product->not_for_sale_from_master,
                'from_trade_unit'    => $product->not_for_sale_from_trade_unit,
                'product_state'      => $product->state->labels()[$product->state->value],
                'product_state_icon' => $product->state->stateIcon()[$product->state->value],
                'parentLink'         => $parentLink,
            ],
            'search_in_website_availability' => $product->webpage ? $this->getSearchInWebsiteAvailabilityChecklist($product) : null,
        ];
    }

    /**
     * Where the product's org stocks physically sit, most stock first.
     *
     * @return array<int, array{location_code: string, warehouse_code: string, org_stock_code: string, quantity: float}>
     */
    private function getStockLocations(Product $product): array
    {
        $orgStockIds = $product->orgStocks->pluck('id')->all();

        if (!$orgStockIds) {
            return [];
        }

        return DB::table('location_org_stocks')
            ->join('locations', 'locations.id', 'location_org_stocks.location_id')
            ->join('warehouses', 'warehouses.id', 'location_org_stocks.warehouse_id')
            ->join('org_stocks', 'org_stocks.id', 'location_org_stocks.org_stock_id')
            ->whereIn('location_org_stocks.org_stock_id', $orgStockIds)
            ->where('location_org_stocks.quantity', '!=', 0)
            ->orderByDesc('location_org_stocks.quantity')
            ->select([
                'locations.code as location_code',
                'warehouses.code as warehouse_code',
                'org_stocks.code as org_stock_code',
                'location_org_stocks.quantity',
            ])
            ->get()
            ->map(fn ($row) => [
                'location_code'  => $row->location_code,
                'warehouse_code' => $row->warehouse_code,
                'org_stock_code' => $row->org_stock_code,
                'quantity'       => (float) $row->quantity,
            ])
            ->all();
    }
}
