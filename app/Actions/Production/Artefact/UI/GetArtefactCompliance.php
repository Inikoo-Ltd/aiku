<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\Production\Artefact\GetArtefactComplianceStatus;
use App\Enums\Production\Artefact\ArtefactComplianceTypeEnum;
use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactCompliance
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        $status = GetArtefactComplianceStatus::run($artefact);

        return [
            'artefact_id'  => $artefact->id,
            'status'       => $status['status'],
            'status_label' => $status['label'],
            'problems'     => $status['problems'],
            'items'        => $artefact->complianceItems->map(fn ($item) => [
                'id'              => $item->id,
                'type'            => $item->type,
                'type_label'      => $item->type->labels()[$item->type->value],
                'reference'       => $item->reference,
                'notes'           => $item->notes,
                'is_required'     => $item->is_required,
                'valid_from'      => $item->valid_from?->toDateString(),
                'valid_until'     => $item->valid_until?->toDateString(),
                'expired'         => (bool) ($item->valid_until && $item->valid_until->isPast()),
                'days_to_expiry'  => $item->valid_until ? (int) now()->startOfDay()->diffInDays($item->valid_until->copy()->startOfDay(), false) : null,
            ]),
            'type_options' => collect(ArtefactComplianceTypeEnum::labels())
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'routes'       => [
                'store'  => [
                    'name'       => 'grp.models.artefact.compliance-item.store',
                    'parameters' => ['artefact' => $artefact->id],
                ],
                'update' => [
                    'name' => 'grp.models.artefact.compliance-item.update',
                ],
                'delete' => [
                    'name' => 'grp.models.artefact.compliance-item.delete',
                ],
            ],
        ];
    }
}
