<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Apr 2025 23:57:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithHumanResourcesEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {

        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(["human-resources.{$this->organisation->id}.edit","org-supervisor.{$this->organisation->id}.human-resources"]);

    }
}
