<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 10:16:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeHasJobPositions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeHasJobPositions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeHasJobPositions query()
 * @mixin \Eloquent
 */
class EmployeeHasJobPositions extends Pivot
{
    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
    ];

    protected $attributes = [
        'scopes'                => '{}',
    ];


}
