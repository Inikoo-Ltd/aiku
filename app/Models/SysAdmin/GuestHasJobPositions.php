<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Aug 2024 11:33:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 *
 *
 * @method static \Illuminate\Database\Eloquent\Builder|GuestHasJobPositions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GuestHasJobPositions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GuestHasJobPositions query()
 * @mixin \Eloquent
 */
class GuestHasJobPositions extends Pivot
{
    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
    ];

    protected $attributes = [
        'scopes'                => '{}',
    ];


}
