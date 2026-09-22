<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Actions\Production\JobOrder\BatchedUnitsForDemand;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\JobOrderItem;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOpenJobOrderItemsOffBatch
{
    use AsObject;

    /**
     * Open job order items nobody has started, whose quantity is not whole batches of what the artefact
     * makes at a time today: they were raised with a batch size that has since changed.
     *
     * @param  array<int, int>  $artefactIds
     * @return Collection<int, array{id: int, job_order_slug: string, job_order_reference: string, artefact_code: string, quantity: int, batch_size: int, demand_skos: float|null, suggested_quantity: int|null}>
     */
    public function handle(array $artefactIds): Collection
    {
        return JobOrderItem::whereIn('artefact_id', $artefactIds)
            ->where('quantity_received', 0)
            ->whereHas('jobOrder', fn ($query) => $query->whereIn('state', JobOrderStateEnum::open()))
            ->whereDoesntHave('tasks', fn ($query) => $query->where('quantity_made', '>', 0))
            ->with(['jobOrder', 'artefact.orgStock'])
            ->get()
            ->filter(fn (JobOrderItem $item) => $item->artefact->recommended_batch_size && $item->quantity % $item->artefact->recommended_batch_size)
            ->map(fn (JobOrderItem $item) => [
                'id'                  => $item->id,
                'job_order_slug'      => $item->jobOrder->slug,
                'job_order_reference' => $item->jobOrder->reference,
                'artefact_code'       => $item->artefact->code,
                'quantity'            => (int) $item->quantity,
                'batch_size'          => $item->artefact->recommended_batch_size,
                'demand_skos'         => $this->getDemandInSkos($item),
                'suggested_quantity'  => $this->getSuggestedQuantity($item),
            ])
            ->values();
    }

    public function getSuggestedQuantity(JobOrderItem $item): ?int
    {
        $demandInSkos = $this->getDemandInSkos($item);

        if ($demandInSkos === null) {
            return null;
        }

        return BatchedUnitsForDemand::run($demandInSkos, $item->artefact->orgStock?->packed_in, $item->artefact->recommended_batch_size);
    }

    private function getDemandInSkos(JobOrderItem $item): ?float
    {
        $demandInSkos = data_get($item->data, 'demand_skos');

        return $demandInSkos === null ? null : (float) $demandInSkos;
    }
}
