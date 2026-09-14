<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:29:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\Production\Artefact\GetArtefactComplianceStatus;
use App\Http\Resources\Production\ArtefactLabelResource;
use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactShowcase
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        $compliance = GetArtefactComplianceStatus::run($artefact);

        return [
            'code'              => $artefact->code,
            'name'              => $artefact->name,
            'state'             => $artefact->state,
            'state_label'       => $artefact->state->labels()[$artefact->state->value],
            'artefact_department'   => $artefact->artefactDepartment ? ['slug' => $artefact->artefactDepartment->slug, 'name' => $artefact->artefactDepartment->name] : null,
            'tags'              => $artefact->tags->pluck('name'),
            'compliance_status' => $compliance['status'],
            'compliance_label'  => $compliance['label'],
            'recommended_batch_size' => $artefact->recommended_batch_size,
            'batch_pack'        => $this->getBatchPack($artefact),
            'shelf_life_days'   => $artefact->shelf_life_days,
            'update_route'      => [
                'name'       => 'grp.models.production.artefacts.update',
                'parameters' => [$artefact->production_id, $artefact->id]
            ],
            'label_sheet'       => [
                'route' => [
                    'name'       => 'grp.models.artefact.label_sheet',
                    'parameters' => ['artefact' => $artefact->id]
                ],
                'store_route' => [
                    'name'       => 'grp.models.artefact.labels.store',
                    'parameters' => ['artefact' => $artefact->id]
                ],
                'update_route' => [
                    'name'       => 'grp.models.artefact.labels.update',
                    'parameters' => ['artefact' => $artefact->id]
                ],
                'delete_route' => [
                    'name'       => 'grp.models.artefact.labels.delete',
                    'parameters' => ['artefact' => $artefact->id]
                ],
                'batch_code'  => $this->getPlaceholderBatchCode($artefact),
                'expiry_date' => $this->getPlaceholderExpiryDate(),
                'labels'      => ArtefactLabelResource::collection($artefact->labels()->with('artwork')->get())->resolve(),
            ],
            'trade_unit' => $artefact->tradeUnit ? [
                'id'   => $artefact->tradeUnit->id,
                'code' => $artefact->tradeUnit->code,
                'name' => $artefact->tradeUnit->name,
            ] : null,
            'org_stock' => $artefact->orgStock ? [
                'id'                     => $artefact->orgStock->id,
                'code'                   => $artefact->orgStock->code,
                'quantity_in_locations'  => (float) $artefact->orgStock->quantity_in_locations,
            ] : null,
            'manufacture_tasks' => $artefact->manufactureTasks->map(fn ($task) => [
                'id'                 => $task->id,
                'code'               => $task->code,
                'name'               => $task->name,
                'position'           => $task->pivot->position,
                'units_per_artefact' => $task->pivot->units_per_artefact,
                'task_work_cost'     => $task->task_work_cost,
            ]),
        ];
    }

    /**
     * The batch is made in units, the org stock is sold in packs, they do not have to agree.
     *
     * @return array{packed_in: int, batch_in_skos: float, suggested_batch_size: int|null}|null
     */
    private function getBatchPack(Artefact $artefact): ?array
    {
        $packedIn = $artefact->orgStock?->packed_in;

        if (!$artefact->recommended_batch_size || !$packedIn) {
            return null;
        }

        $remainder = $artefact->recommended_batch_size % $packedIn;

        return [
            'packed_in'            => $packedIn,
            'batch_in_skos'        => round($artefact->recommended_batch_size / $packedIn, 2),
            'suggested_batch_size' => $remainder
                ? max($packedIn, (int) round($artefact->recommended_batch_size / $packedIn) * $packedIn)
                : null,
        ];
    }

    /**
     * Artefacts have no batch code column yet, this is the stand in until the real one is stored.
     */
    private function getPlaceholderBatchCode(Artefact $artefact): string
    {
        return strtoupper($artefact->code).'-'.now()->format('ymd');
    }

    /**
     * Artefacts have no expiry date column yet, this is the stand in until the real one is stored.
     */
    private function getPlaceholderExpiryDate(): string
    {
        return now()->addYear()->format('d/m/Y');
    }
}
