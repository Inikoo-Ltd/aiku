<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Models\Production\JobOrderItem;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMixJobOrders
{
    use AsObject;

    /** @return array<int, array{id: int, artefact_id: int, code: string, name: string, quantity: float, job_order_id: int, job_order_reference: string, job_order_slug: string, job_order_state: string, job_order_artisan: string|null}> */
    public function handle(Production $production): array
    {
        $mixArtefactIds = RawMaterial::query()
            ->where('production_id', $production->id)
            ->whereNotNull('artefact_id')
            ->pluck('artefact_id');

        return JobOrderItem::query()
            ->whereIn('artefact_id', $mixArtefactIds)
            ->whereHas('jobOrder', fn ($query) => $query->where('production_id', $production->id))
            ->with(['artefact', 'jobOrder.employee'])
            ->get()
            ->map(fn (JobOrderItem $item) => [
                'id'                  => $item->id,
                'artefact_id'         => $item->artefact_id,
                'code'                => $item->artefact->code,
                'name'                => $item->artefact->name,
                'quantity'            => (float) $item->quantity,
                'job_order_id'        => $item->job_order_id,
                'job_order_reference' => $item->jobOrder->reference,
                'job_order_slug'      => $item->jobOrder->slug,
                'job_order_state'     => $item->jobOrder->state->value,
                'job_order_artisan'   => $item->jobOrder->employee?->contact_name,
            ])
            ->all();
    }
}
