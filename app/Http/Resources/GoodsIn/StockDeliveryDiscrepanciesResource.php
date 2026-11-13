<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\GoodsIn;

use App\Actions\GoodsIn\StockDeliveryFollowUp\UI\IndexBuyerDeliveryDiscrepancies;
use App\Enums\GoodsIn\StockDeliveryClaimStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyOutcomeEnum;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One row of "My delivery discrepancies", read off the columns IndexBuyerDeliveryDiscrepancies selects.
 */
class StockDeliveryDiscrepanciesResource extends JsonResource
{
    public function toArray($request): array
    {
        $row      = (object) $this->resource->getAttributes();
        $expected = (float) $row->unit_quantity;
        $received = (float) $row->unit_quantity_checked;

        return [
            'id'                        => $row->id,
            'organisation_code'         => $row->organisation_code,
            'stock_delivery_reference'  => $row->stock_delivery_reference,
            'purchase_order_references' => $row->purchase_order_references,
            'supplier_code'             => $row->supplier_code,
            'supplier_name'             => $row->supplier_name,
            'agent_code'                => $row->agent_code,
            'org_stock_code'            => $row->org_stock_code,
            'org_stock_name'            => $row->org_stock_name,
            'expected_quantity'         => $expected,
            'received_quantity'         => $received,
            'difference'                => $received - $expected,
            'claim_amount'              => $row->claim_amount === null ? null : (float) $row->claim_amount,
            'claim_currency_code'       => $row->claim_currency_code,
            'claim_state'               => $row->claim_state,
            'claim_state_label'         => $row->claim_state ? StockDeliveryClaimStateEnum::labels()[$row->claim_state] : null,
            'outcome'                   => $row->discrepancy_outcome,
            'outcome_label'             => $row->discrepancy_outcome ? StockDeliveryItemDiscrepancyOutcomeEnum::labels()[$row->discrepancy_outcome] : null,
            'status'                    => $row->status,
            'status_label'              => IndexBuyerDeliveryDiscrepancies::statusLabels()[$row->status],
            'checked_at'                => $row->checked_at,
            'age_days'                  => (int) Carbon::parse($row->checked_at)->diffInDays(now()),
            'buyer_id'                  => $row->buyer_id === null ? null : (int) $row->buyer_id,
            'buyer_name'                => $row->buyer_name,
            'stock_delivery_route'      => [
                'name'       => 'grp.org.procurement.stock_deliveries.show',
                'parameters' => ['organisation' => $row->organisation_slug, 'stockDelivery' => $row->stock_delivery_slug, 'tab' => StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value],
            ],
        ];
    }
}
