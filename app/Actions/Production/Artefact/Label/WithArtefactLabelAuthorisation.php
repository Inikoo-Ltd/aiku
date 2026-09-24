<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\Label;

use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use Lorisleiva\Actions\ActionRequest;

/**
 * Labels are the compliance team's work, reached from the SKO. The factory keeps the rights it had on
 * its own artefacts, so a label opened from an artefact also accepts the production permissions.
 */
trait WithArtefactLabelAuthorisation
{
    protected function canViewLabels(ActionRequest $request): bool
    {
        return $request->user()->authTo(array_merge(
            [GroupPermissionsEnum::COMPLIANCE_VIEW->value, "org-supervisor.{$this->organisation->id}"],
            $this->productionLabelPermissions()
        ));
    }

    protected function canEditLabels(ActionRequest $request): bool
    {
        return $request->user()->authTo(array_merge(
            [GroupPermissionsEnum::COMPLIANCE_EDIT->value],
            $this->productionLabelPermissions()
        ));
    }

    protected function canPublishLabels(ActionRequest $request): bool
    {
        return $request->user()->authTo(array_merge(
            [GroupPermissionsEnum::COMPLIANCE_PUBLISH->value],
            $this->productionLabelPermissions()
        ));
    }

    /**
     * @return array<int, string>
     */
    private function productionLabelPermissions(): array
    {
        if (!isset($this->production)) {
            return [];
        }

        return ["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"];
    }
}
