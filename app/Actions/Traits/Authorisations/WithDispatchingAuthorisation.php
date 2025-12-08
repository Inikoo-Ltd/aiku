<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Mar 2025 22:05:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithDispatchingAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {

        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("dispatching.{$this->warehouse->id}.view");

    }
}
