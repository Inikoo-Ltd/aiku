<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderCompliance;
use App\Models\Goods\TradeUnit;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TradeUnit
 */
class PurchaseOrderComplianceDocumentsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TradeUnit $tradeUnit */
        $tradeUnit = $this->resource;

        return [
            'id'   => $tradeUnit->id,
            'code' => $tradeUnit->code,
            'name' => $tradeUnit->name,
            'route' => [
                'name'       => 'grp.trade_units.units.show',
                'parameters' => [$tradeUnit->slug],
            ],
            ...collect(GetPurchaseOrderCompliance::make()->documents($tradeUnit)['documents'])->keyBy('scope')->all(),
        ];
    }
}
