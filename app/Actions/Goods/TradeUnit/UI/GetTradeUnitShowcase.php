<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Goods\TradeUnit\UI;

use App\Helpers\NaturalLanguage;
use App\Http\Resources\Catalogue\TagsResource;
use App\Http\Resources\Goods\IngredientsResource;
use App\Http\Resources\Goods\TradeUnitResource;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitShowcase
{
    use AsObject;

    public function handle(TradeUnit $tradeUnit): array
    {
        $brandRoute = [
            'index_brand' => [
                'name'       => 'grp.json.brands.index',
                'parameters' => []
            ],
            'store_brand' => [
                'name'       => 'grp.models.trade-unit.brands.store',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ]
            ],
            'update_brand' => [
                'name'       => 'grp.models.trade-unit.brands.update',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'patch'
            ],
            'delete_brand' => [
                'name'       => 'grp.models.trade-unit.brands.delete',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'delete'
            ],
            'attach_brand' => [
                'name'       => 'grp.models.trade-unit.brands.attach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'post'
            ],
            'detach_brand' => [
                'name'       => 'grp.models.trade-unit.brands.detach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                    'brand' => $tradeUnit->brand()?->id,
                ],
                'method'    => 'delete'
            ],
        ];
        $tagRoute = [
            'index_tag' => [
                'name'       => 'grp.json.trade_units.tags.index',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ]
            ],
            'store_tag' => [
                'name'       => 'grp.models.trade-unit.tags.store',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ]
            ],
            'update_tag' => [
                'name'       => 'grp.models.trade-unit.tags.update',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'patch'
            ],
            'delete_tag' => [
                'name'       => 'grp.models.trade-unit.tags.delete',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'delete'
            ],
            'attach_tag' => [
                'name'       => 'grp.models.trade-unit.tags.attach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'post'
            ],
            'detach_tag' => [
                'name'       => 'grp.models.trade-unit.tags.detach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'delete'
            ],
        ];

        $properties = [
            'country_of_origin' => NaturalLanguage::make()->country($tradeUnit->country_of_origin),
            'ingredients'       => IngredientsResource::collection($tradeUnit->ingredients)->resolve(),
            'tariff_code'       => $tradeUnit->tariff_code,
            'duty_rate'         => $tradeUnit->duty_rate,
            'hts_us'            => $tradeUnit->hts_us,
        ];

        $gpsr = [
            'manufacturer' => $tradeUnit->gpsr_manufacturer,
            'eu_responsible' => $tradeUnit->gpsr_eu_responsible,
            'warnings' => $tradeUnit->gpsr_warnings,
            'how_to_use' => $tradeUnit->gpsr_manual,
            'gpsr_class_category_danger' => $tradeUnit->gpsr_class_category_danger,
            'trad$tradeUnit_languages' => $tradeUnit->gpsr_class_languages,
            'acute_toxicity' => $tradeUnit->pictogram_toxic,
            'corrosive' => $tradeUnit->pictogram_corrosive,
            'explosive' => $tradeUnit->pictogram_explosive,
            'flammable' => $tradeUnit->pictogram_flammable,
            'gas_under_pressure' => $tradeUnit->pictogram_gas,
            'hazard_environment' => $tradeUnit->pictogram_environment,
            'health_hazard' => $tradeUnit->pictogram_health,
            'oxidising' => $tradeUnit->pictogram_oxidising,
        ];

        return [
            'properties' => $properties,
            'gpsr'  => $gpsr,
            'tradeUnit' => TradeUnitResource::make($tradeUnit)->toArray(request()),
            'brand_routes' => $brandRoute,
            'brand' => $tradeUnit->brand(),
            'tag_routes' => $tagRoute,
            'tags_selected_id' => $tradeUnit->tags->pluck('id')->toArray(),
            'tags' =>  TagsResource::collection($tradeUnit->tags)->toArray(request()),
            'translation_box' => [
                'title' => __('Multi-language Translations'),
                'save_route' => [
                    'name' => 'grp.models.trade-unit.translations.update',
                    'parameters' => [
                        'tradeUnit' => $tradeUnit->id,
                    ],
                ],
            ],
            'images' => $this->getImagesData($tradeUnit),
        ];
    }

    public function getImagesData(TradeUnit $tradeUnit): array
    {

        return [
            [
                'label' => __('Main'),
                'type'  => 'image',
                'column_in_db' => 'image_id',
                'images' => $tradeUnit->imageSources(),
            ],
            [
                'label' => __('Video'),
                'type'  => 'video',
                'column_in_db' => 'video_url',
                'url' => $tradeUnit->video_url,
            ],
            [
                'label' => __('Front side'),
                'type'  => 'image',
                'column_in_db' => 'front_image_id',
                'images' => $tradeUnit->imageSources(getImage:'frontImage'),
            ],
            [
                'label' => __('Left side'),
                'type'  => 'image',
                'column_in_db' => 'left_image_id',
                'images' => $tradeUnit->imageSources(getImage:'leftImage'),
            ],
            [
                'label' => __('3/4 angle side'),
                'type'  => 'image',
                'column_in_db' => '34_image_id',
                'images' => $tradeUnit->imageSources(getImage:'threeQuarterImage'),
            ],
            [
                'label' => __('Right side'),
                'type'  => 'image',
                'column_in_db' => 'right_image_id',
                'images' => $tradeUnit->imageSources(getImage:'rightImage'),
            ],
            [
                'label' => __('Back side'),
                'type'  => 'image',
                'column_in_db' => 'back_image_id',
                'images' => $tradeUnit->imageSources(getImage:'backImage'),
            ],
            [
                'label' => __('Top side'),
                'type'  => 'image',
                'column_in_db' => 'top_image_id',
                'images' => $tradeUnit->imageSources(getImage:'topImage'),
            ],
            [
                'label' => __('Bottom side'),
                'type'  => 'image',
                'column_in_db' => 'bottom_image_id',
                'images' => $tradeUnit->imageSources(getImage:'bottomImage'),
            ],
            [
                'label' => __('Comparison image'),
                'type'  => 'image',
                'column_in_db' => 'size_comparison_image_id',
                'images' => $tradeUnit->imageSources(getImage:'sizeComparisonImage'),
            ],
        ];


    }

}
