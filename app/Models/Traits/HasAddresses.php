<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 02 Jan 2022 15:30:28 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Models\Traits;

use App\Models\Helpers\Address;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasAddresses
{
    public function addresses(): MorphToMany
    {
        return $this->morphToMany(Address::class, 'model', 'model_has_addresses')->withPivot('scope', 'sub_scope', 'label', 'can_ship', 'is_historic', 'valid_until')->withTimestamps();
    }
}
