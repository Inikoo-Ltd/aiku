<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Enums\Production\Artefact\ArtefactComplianceStatusEnum;
use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactComplianceStatus
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        $items = $artefact->complianceItems;

        if ($items->isEmpty()) {
            return [
                'status'   => ArtefactComplianceStatusEnum::NOT_CONFIGURED,
                'label'    => ArtefactComplianceStatusEnum::NOT_CONFIGURED->labels()[ArtefactComplianceStatusEnum::NOT_CONFIGURED->value],
                'problems' => [],
            ];
        }

        $problems = [];
        foreach ($items as $item) {
            if (!$item->is_required) {
                continue;
            }

            if (blank($item->reference)) {
                $problems[] = $item->type->labels()[$item->type->value].': '.__('no document');

                continue;
            }

            if ($item->valid_until && $item->valid_until->isPast()) {
                $problems[] = $item->type->labels()[$item->type->value].' '.$item->reference.': '.__('expired :date', ['date' => $item->valid_until->toDateString()]);
            }
        }

        if ($problems) {
            return [
                'status'   => ArtefactComplianceStatusEnum::PROBLEM,
                'label'    => ArtefactComplianceStatusEnum::PROBLEM->labels()[ArtefactComplianceStatusEnum::PROBLEM->value],
                'problems' => $problems,
            ];
        }

        $expiring = [];
        foreach ($items as $item) {
            if (!$item->valid_until) {
                continue;
            }

            $daysToExpiry = (int) now()->startOfDay()->diffInDays($item->valid_until->startOfDay(), false);
            if ($daysToExpiry >= 0 && $daysToExpiry <= 30) {
                $expiring[] = $item->type->labels()[$item->type->value].': '.__('expires in :days days', ['days' => $daysToExpiry]);
            }
        }

        if ($expiring) {
            return [
                'status'   => ArtefactComplianceStatusEnum::EXPIRING,
                'label'    => ArtefactComplianceStatusEnum::EXPIRING->labels()[ArtefactComplianceStatusEnum::EXPIRING->value],
                'problems' => $expiring,
            ];
        }

        return [
            'status'   => ArtefactComplianceStatusEnum::OK,
            'label'    => ArtefactComplianceStatusEnum::OK->labels()[ArtefactComplianceStatusEnum::OK->value],
            'problems' => [],
        ];
    }
}
