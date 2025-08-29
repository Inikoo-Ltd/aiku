<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use App\Http\Resources\Goods\TradeUnitsForMasterResource;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property string $name
 * @property mixed $master_shop_slug
 * @property mixed $master_shop_code
 * @property mixed $master_shop_name
 * @property mixed $master_department_slug
 * @property mixed $master_department_code
 * @property mixed $master_department_name
 * @property mixed $master_family_slug
 * @property mixed $master_family_code
 * @property mixed $master_family_name
 */
class MasterProductResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'slug'                   => $this->slug,
            'code'                   => $this->code,
            'name'                   => $this->name,
            'price'                 => $this->price,
            'description'           => $this->description,
            'description_title'     => $this->description_title,
            'description_extra'     => $this->description_extra,
            'trade_units'           => TradeUnitsForMasterResource::collection($this->tradeUnits)->resolve(),
            'products'               => MasterProductProductsResource::collection($this->products)->resolve(),
            'name_i8n'              => $this->getTranslations('name_i8n'),
            'description_i8n'       => $this->getTranslations('description_i8n'),
            'description_title_i8n' => $this->getTranslations('description_title_i8n'),
            'description_extra_i8n' => $this->getTranslations('description_extra_i8n'),
            'translation_box' => [
                'title' => __('Multi-language Translations'),
                'save_route' => [
                'name'       => 'grp.models.master-product.translations.update',
                'parameters' => []
                ],
            ],

        ];
    }
}
