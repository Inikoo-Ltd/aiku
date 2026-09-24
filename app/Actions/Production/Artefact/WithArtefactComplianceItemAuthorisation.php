<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Production\ArtefactComplianceItem;
use Lorisleiva\Actions\ActionRequest;

/**
 * Compliance items belong to the SKO and are the compliance team's to keep. The factory keeps the
 * rights it had over the items of its own artefacts.
 */
trait WithArtefactComplianceItemAuthorisation
{
    protected function canEditComplianceItems(ActionRequest $request): bool
    {
        $permissions = [GroupPermissionsEnum::COMPLIANCE_EDIT->value];

        if (isset($this->production)) {
            $permissions = array_merge($permissions, [
                'org-supervisor.'.$this->organisation->id,
                'productions-view.'.$this->organisation->id,
                "productions_operations.{$this->production->id}.view",
                "productions_operations.{$this->production->id}.orchestrate",
            ]);
        }

        return $request->user()->authTo($permissions);
    }

    protected function initialisationFromComplianceItem(ArtefactComplianceItem $artefactComplianceItem, ActionRequest|array $request): void
    {
        if ($artefactComplianceItem->artefact) {
            $this->initialisationFromProduction($artefactComplianceItem->artefact->production, $request);

            return;
        }

        $this->initialisation($artefactComplianceItem->organisation, $request);
    }
}
