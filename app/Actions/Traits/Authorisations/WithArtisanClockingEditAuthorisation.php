<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

/**
 * HR changes clockings. The accountant and the production manager (accounting edit) reach them too, but ChangeArtisanClocking
 * lets them change only artisans' weeks already locked for payroll, the ones HR no longer can.
 */
trait WithArtisanClockingEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            "human-resources.{$this->organisation->id}.edit",
            "org-supervisor.{$this->organisation->id}.human-resources",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }
}
