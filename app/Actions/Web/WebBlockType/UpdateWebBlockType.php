<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Jul 2023 15:12:19 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlockType;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\WebBlockType;

class UpdateWebBlockType
{
    use WithActionUpdate;

    public function handle(WebBlockType $webBlock, array $modelData): WebBlockType
    {
        return $this->update($webBlock, $modelData);
    }
}
