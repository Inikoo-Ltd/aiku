<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Actions\Inventory\OrgStock\CheckStockValues;
use App\Models\Inventory\StockValueFinding;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockValueFinding
 */
class StockValueFindingsResource extends JsonResource
{
    public function toArray($request): array
    {
        $raised = match (true) {
            $this->staffTask !== null => ['reference' => $this->staffTask->reference, 'url' => route('grp.tasks.index', ['task' => $this->staffTask->reference])],
            $this->ticket !== null    => ['reference' => $this->ticket->reference, 'url' => route('grp.tickets.show', $this->ticket->reference)],
            default                   => null,
        };

        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'slug'          => $this->slug,
            'name'          => $this->name,
            'state'         => $this->state,
            'explanation'   => $this->explanation ?? CheckStockValues::make()->ruleText($this->resource),
            'action'        => CheckStockValues::actionText($this->resource),
            'owner'         => [
                StockValueFinding::OWNER_WAREHOUSE   => __('Warehouse'),
                StockValueFinding::OWNER_PROCUREMENT => __('Procurement'),
                StockValueFinding::OWNER_ENGINEERING => __('Engineers'),
                StockValueFinding::OWNER_LEGACY      => __('Old data'),
            ][$this->owner] ?? $this->owner,
            'excess_amount' => (float)$this->excess_amount,
            'raised'        => $raised,
            'first_seen_at' => $this->first_seen_at,
        ];
    }
}
