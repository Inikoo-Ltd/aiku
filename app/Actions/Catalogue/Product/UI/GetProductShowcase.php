<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:53:47 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use App\Actions\Traits\HasBucketImages;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Inventory\OrgStock\Json\GetOrgStocksInProduct;
use App\Actions\Traits\HasBucketAttachment;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Inventory\OrgStocksResource;

class GetProductShowcase
{
    use AsObject;
    use HasBucketImages;
    use HasBucketAttachment;

    public function handle(Product $product): array
    {
        $webpageUrl = null;
        if ($product->webpage && $product->webpage->state == WebpageStateEnum::LIVE) {
            $webpageUrl = $product->webpage->canonical_url;
        }

        $tradeUnits = $product->tradeUnits;


        $tradeUnits->loadMissing(['ingredients']);


        $properties = [
            'country_of_origin' => NaturalLanguage::make()->country($product->country_of_origin),
            'ingredients'       => $product->marketing_ingredients,
            'tariff_code'       => $product->tariff_code,
            'duty_rate'         => $product->duty_rate,
            'hts_us'            => $product->hts_us,
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

        $dataTradeUnits = [];
        if ($product->tradeUnits) {
            $dataTradeUnits = $this->getDataTradeUnit($product->tradeUnits);
        }

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
            'product'             => ProductResource::make($product),
            'properties'          => $properties,
            'gpsr'                => $gpsr,
            'parts'               => OrgStocksResource::collection(GetOrgStocksInProduct::run($product))->resolve(),
            'stats'               => $product->stats,
            'trade_units'         => $dataTradeUnits,
            'images'              => $this->getImagesData($product),
            'main_image'          => $product->imageSources(),
            'attachment_box'      => $this->getAttachmentData($product),
            'webpage_url'         => $webpageUrl,
            'availability_status' => [
                'is_for_sale'        => $product->is_for_sale,
                'from_master'        => $product->not_for_sale_from_master,
                'from_trade_unit'    => $product->not_for_sale_from_trade_unit,
                'product_state'      => $product->state->labels()[$product->state->value],
                'product_state_icon' => $product->state->stateIcon()[$product->state->value],
                'parentLink'         => $parentLink,
            ],
        ];
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return GetTradeUnitShowcase::run($tradeUnit);
        })->toArray();
    }

}
