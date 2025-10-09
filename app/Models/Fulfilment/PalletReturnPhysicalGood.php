<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:39:14 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturnPhysicalGood newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturnPhysicalGood newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturnPhysicalGood query()
 * @mixin \Eloquent
 */
class PalletReturnPhysicalGood extends Pivot
{
}
