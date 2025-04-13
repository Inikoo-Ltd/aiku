<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 25 Oct 2022 08:17:00 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property number $state
 * @property string $name
 * @property string $description
 * @property string $number_current_stocks
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
 *
 */
class StockFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                     => $this->slug,
            'code'                     => $this->code,
            'state'                    => $this->state,
            'name'                     => $this->name,
            'number_current_stocks'    => $this->number_current_stocks,
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
            'grp_currency'             => $this->grp_currency_code


        ];
    }
}
