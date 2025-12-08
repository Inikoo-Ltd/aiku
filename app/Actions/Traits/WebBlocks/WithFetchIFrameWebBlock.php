<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Oct 2024 12:37:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\WebBlocks;

use Lorisleiva\Actions\Concerns\AsAction;

trait WithFetchIFrameWebBlock
{
    use AsAction;

    public function processIFrameData($auroraBlock): array
    {
        data_set($layout, 'data.fieldValue.link', 'https://'.$auroraBlock['src']);

        return $layout;
    }
}
