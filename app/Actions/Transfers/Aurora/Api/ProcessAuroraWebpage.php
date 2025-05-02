<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Apr 2025 23:34:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora\Api;

use App\Actions\OrgAction;
use App\Actions\Transfers\Aurora\FetchAuroraWebpages;

/**
 * @property string $fetcher
 */
class ProcessAuroraWebpage extends OrgAction
{
    use WithProcessAurora;

    public function __construct()
    {
        $this->fetcher = FetchAuroraWebpages::class;
    }

}
