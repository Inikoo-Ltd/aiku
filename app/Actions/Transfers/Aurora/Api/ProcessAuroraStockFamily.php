<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Feb 2025 17:15:05 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora\Api;

use App\Actions\OrgAction;
use App\Actions\Transfers\Aurora\FetchAuroraStockFamilies;

/**
 * @property string $fetcher
 */
class ProcessAuroraStockFamily extends OrgAction
{
    use WithProcessAurora;

    public function __construct()
    {
        $this->fetcher = FetchAuroraStockFamilies::class;
    }
}
