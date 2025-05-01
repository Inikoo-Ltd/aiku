<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 May 2025 14:20:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Purge;

use App\Actions\Ordering\Purge\Hydrators\PurgeHydratePurgedOrders;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Ordering\Purge;

class HydratePurges
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:purges {organisations?*} {--s|slugs=}';


    public function handle(Purge $purge): void
    {
        PurgeHydratePurgedOrders::run($purge);

    }

    public function __construct()
    {
        $this->model = Purge::class;
    }
}
