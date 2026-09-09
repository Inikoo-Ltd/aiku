<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 16:10:36 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $code
 */
class RawMaterialsResource extends JsonResource
{
    public function toArray($request): array
    {
        $state       = $this->enumValue($this->state);
        $stockStatus = $this->enumValue($this->stock_status);
        $type        = $this->enumValue($this->type);
        $unit        = $this->enumValue($this->unit);

        return [
            'id'                   => $this->id,
            'slug'                 => $this->slug,
            'code'                 => $this->code,
            'description'          => $this->description,
            'state'                => $state ? RawMaterialStateEnum::stateIcon()[$state] : null,
            'type'                 => $type ? RawMaterialTypeEnum::labels()[$type] ?? $type : null,
            'unit'                 => $unit ? RawMaterialUnitEnum::labels()[$unit] ?? $unit : null,
            'unit_cost'            => $this->unit_cost,
            'quantity_on_location' => $this->quantity_on_location,
            'stock_status'         => $stockStatus ? RawMaterialStockStatusEnum::stockStatusIcon()[$stockStatus] : null,
            'number_artefacts'     => (int) $this->number_artefacts,
            'currency_code'        => $this->currency_code,
            'organisation_name'    => $this->organisation_name,
            'organisation_slug'    => $this->organisation_slug
        ];
    }

    private function enumValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
