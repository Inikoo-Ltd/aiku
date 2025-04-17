<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Apr 2025 11:32:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Inventory;

use Lorisleiva\Actions\ActionRequest;

trait WithGroupOverviewAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("group-overview");
    }
}
