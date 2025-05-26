<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Apr 2025 23:57:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;


trait WithHumanResourcesAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (str_starts_with($request->route()->getName(), 'grp.overview.hr.')) {
            return $request->user()->authTo("group-overview");
        }

        $this->canEdit = $request->user()->authTo(["human-resources.{$this->organisation->id}.edit", "org-supervisor.{$this->organisation->id}.human-resources"]);

        return $request->user()->authTo(["human-resources.{$this->organisation->id}.view", "org-supervisor.{$this->organisation->id}.human-resources"]);
    }
}
