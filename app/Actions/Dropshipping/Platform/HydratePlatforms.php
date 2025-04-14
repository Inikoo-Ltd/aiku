<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 18:29:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Platform;

use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateCustomers;
use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateOrders;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Dropshipping\Platform;

class HydratePlatforms
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:platforms';

    public function __construct()
    {
        $this->model = Platform::class;
    }

    public function handle(Platform $platform): void
    {
        PlatformHydrateOrders::run($platform);
        PlatformHydrateCustomers::run($platform);
    }


}
