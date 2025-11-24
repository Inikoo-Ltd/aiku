<?php

namespace App\Http\Resources\Masters;

use App\Actions\Traits\HasBucketImages;
use App\Http\Resources\Goods\TradeUnitsForMasterResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\NaturalLanguage;
use App\Actions\Traits\HasBucketAttachment;

class MasterProductResource extends JsonResource
{
    use HasBucketImages;
    use HasBucketAttachment;

    public function toArray($request): array
    {
        $tradeUnits = $this->tradeUnits;

        $tradeUnits->loadMissing('ingredients');

        $ingredients = $tradeUnits->flatMap(function ($tradeUnit) {
            return $tradeUnit->ingredients->pluck('name');
        })->unique()->values()->all();


        return [
            'slug'                  => $this->slug,
            'code'                  => $this->code,
            'name'                  => $this->name,
            'currency'              => $this->group->currency->code,
            'price'                 => $this->price,
            'description'           => $this->description,
            'description_title'     => $this->description_title,
            'stock'                 => $this->stock,
            'specifications'        => [
                'gross_weight' => $this->marketing_weight,
            ],
            'description_extra'     => $this->description_extra,
            'units'                 => $this->units,
            'unit'                  => $this->unit,
            'trade_units'           => TradeUnitsForMasterResource::collection($tradeUnits)->resolve(),
            'name_i8n'              => $this->getTranslations('name_i8n'),
            'description_i8n'       => $this->getTranslations('description_i8n'),
            'description_title_i8n' => $this->getTranslations('description_title_i8n'),
            'description_extra_i8n' => $this->getTranslations('description_extra_i8n'),
            'marketing_ingredients'         => $ingredients,
            'country_of_origin'             => NaturalLanguage::make()->country($this->tradeUnits()->first()?->country_of_origin)
        ];
    }
}
