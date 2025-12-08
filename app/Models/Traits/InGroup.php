<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 May 2024 21:28:43 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait InGroup
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
