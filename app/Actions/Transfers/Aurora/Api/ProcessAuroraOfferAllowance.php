<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Feb 2025 17:09:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora\Api;

use App\Actions\OrgAction;
use App\Actions\Transfers\Aurora\FetchAuroraOfferComponents;

/**
 * @property string $fetcher
 */
class ProcessAuroraOfferAllowance extends OrgAction
{
    use WithProcessAurora;

    public function __construct()
    {
        $this->fetcher = FetchAuroraOfferComponents::class;
    }

}
