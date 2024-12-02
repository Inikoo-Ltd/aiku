<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Apr 2024 08:17:20 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait InShop
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
