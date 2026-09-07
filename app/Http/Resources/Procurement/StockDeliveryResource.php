<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Helpers\NaturalLanguage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $parent_name
 * @property mixed $state
 * @property mixed $date
 * @property mixed $organisation_name
 * @property mixed $number_stock_delivery_items_except_cancelled
 * @property mixed $number_stock_delivery_items
 * @property mixed $cbm
 * @property mixed $gross_weight
 * @property mixed $cost_total
 * @property mixed $currency_code
 * @property mixed $grp_exchange
 * @property mixed $org_exchange
 * @property mixed $org_currency_code
 * @property mixed $grp_currency_code
 */
class StockDeliveryResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'reference'          => $this->reference,
            'parent_name'        => $this->parent_name,
            'state'              => $this->state,
            'state_icon'         => $this->state->stateIcon()[$this->state->value],
            'state_label'        => StockDeliveryStateEnum::labels()[$this->state->value],
            'date'               => $this->date,
            'organisation_name'  => $this->organisation_name,
            'organisation_slug'  => $this->organisation_slug,
        ];

        if (isset($this->currency_code)) {
            $data['items']        = $this->number_stock_delivery_items_except_cancelled ?: $this->number_stock_delivery_items;
            $data['cbm']          = $this->cbm;
            $data['gross_weight'] = $this->gross_weight !== null ? NaturalLanguage::make()->weight($this->gross_weight * 1000) : null;
            $data['amount']       = $this->cost_total;
            $data['currency_code'] = $this->currency_code;

            $onGroupRoute = str_starts_with($request->route()?->getName() ?? '', 'grp.supply-chain.');
            $exchange     = $onGroupRoute ? $this->grp_exchange : $this->org_exchange;

            $data['converted_amount']        = $this->cost_total !== null && $exchange !== null ? $this->cost_total * $exchange : null;
            $data['converted_currency_code'] = $onGroupRoute ? $this->grp_currency_code : $this->org_currency_code;
        }

        return $data;
    }
}
