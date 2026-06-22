<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 Jun 2026 14:02:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class ReopenDiscontinuedWebpage
{
    use AsAction;

    public function handle(Webpage $webpage): void
    {

        if (!$webpage->model instanceof Product) {
            return;
        }
        ReopenWebpage::run($webpage);

    }
}
