<?php

namespace App\Http\Resources\Masters;

use App\Actions\Traits\HasBucketAttachment;
use App\Actions\Traits\HasBucketImages;
use App\Helpers\NaturalLanguage;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterProductResource extends JsonResource
{
    use HasBucketAttachment;
    use HasBucketImages;

    public function toArray($request): array
    {
        $tradeUnits = $this->tradeUnits;

        $tradeUnits->loadMissing('ingredients');

        $ingredients = $tradeUnits->flatMap(function ($tradeUnit) {
            return $tradeUnit->ingredients->pluck('name');
        })->unique()->values()->all();

        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->group->currency->code,
            'description' => $this->description,
            'description_title' => $this->description_title,
            'stock' => $this->stock,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'description_extra' => $this->description_extra,
            'units' => (int) $this->units,
            'unit' => $this->unit,
            'name_i8n' => $this->getTranslations('name_i8n'),
            'description_i8n' => $this->getTranslations('description_i8n'),
            'description_title_i8n' => $this->getTranslations('description_title_i8n'),
            'description_extra_i8n' => $this->getTranslations('description_extra_i8n'),
            'marketing_ingredients' => $ingredients,
            'country_of_origin' => NaturalLanguage::make()->country($this->tradeUnits()->first()?->country_of_origin),
            'marketing_dimensions' => NaturalLanguage::make()->dimensions($this->marketing_dimensions),
            'marketing_ingredients' => $this->marketing_ingredients,
            'marketing_weight' => NaturalLanguage::make()->weight($this->marketing_weight),
            'gross_weight' => NaturalLanguage::make()->weight($this->gross_weight),
            'cpnp_number' => $this->cpnp_number,
            'ufi_number' => $this->ufi_number,
            'scpn_number' => $this->scpn_number,
            'hts_us' => $this->hts_us,
            'un_number' => $this->un_number,
            'un_class' => $this->un_class,
            'packing_group' => $this->packing_group,
            'proper_shipping_name' => $this->proper_shipping_name,
            'hazard_identification_number' => $this->hazard_identification_number,
        ];
    }
}
