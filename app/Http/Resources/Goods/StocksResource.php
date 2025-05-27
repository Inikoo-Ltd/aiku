<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property number $quantity_in_locations
 * @property number $number_location
 * @property number $unit_value
 * @property string $slug
 * @property string $description
 * @property string $family_slug
 * @property string $family_code
 * @property string $name
 * @property mixed $revenue_grp_currency_all
 * @property mixed $revenue_grp_currency_1y
 * @property mixed $revenue_grp_currency_1q
 * @property mixed $revenue_grp_currency_1m
 * @property mixed $revenue_grp_currency_1w
 * @property mixed $revenue_grp_currency_ytd
 * @property mixed $revenue_grp_currency_mtd
 * @property mixed $revenue_grp_currency_wtd
 * @property mixed $revenue_grp_currency_3d
 * @property mixed $revenue_grp_currency_1d
 * @property mixed $revenue_grp_currency_qtd
 * @property mixed $revenue_grp_currency_lm
 * @property mixed $revenue_grp_currency_tdy
 * @property mixed $revenue_grp_currency_ld
 * @property mixed $grp_currency_code
 */
class StocksResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'slug'                     => $this->slug,
            'code'                     => $this->code,
            'name'                     => $this->name,
            'unit_value'               => $this->unit_value,
            'family_slug'              => $this->family_slug,
            'family_code'              => $this->family_code,
            'revenue_grp_currency_all' => $this->revenue_grp_currency_all,
            'revenue_grp_currency_1y'  => $this->revenue_grp_currency_1y,
            'revenue_grp_currency_1q'  => $this->revenue_grp_currency_1q,
            'revenue_grp_currency_1m'  => $this->revenue_grp_currency_1m,
            'revenue_grp_currency_1w'  => $this->revenue_grp_currency_1w,
            'revenue_grp_currency_3d'  => $this->revenue_grp_currency_3d,
            'revenue_grp_currency_1d'  => $this->revenue_grp_currency_1d,
            'revenue_grp_currency_ytd' => $this->revenue_grp_currency_ytd,
            'revenue_grp_currency_qtd' => $this->revenue_grp_currency_qtd,
            'revenue_grp_currency_mtd' => $this->revenue_grp_currency_mtd,
            'revenue_grp_currency_wtd' => $this->revenue_grp_currency_wtd,
            'revenue_grp_currency_tdy' => $this->revenue_grp_currency_tdy,
            'revenue_grp_currency_lm'  => $this->revenue_grp_currency_lm,
            'revenue_grp_currency_lw'  => $this->revenue_grp_currency_1w,
            'revenue_grp_currency_ld'  => $this->revenue_grp_currency_ld,
            'grp_currency'             => $this->grp_currency_code,
            'state'                    => $this->state,
        ];
    }
}
