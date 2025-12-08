<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 12 Sept 2022 18:06:24 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * App\Models\HumanResources\EmployeeJobPosition
 *
 * @property-read \App\Models\HumanResources\Employee|null $employee
 * @property-read \App\Models\HumanResources\JobPosition|null $jobPosition
 *
 * @method static Builder<static>|EmployeeJobPosition newModelQuery()
 * @method static Builder<static>|EmployeeJobPosition newQuery()
 * @method static Builder<static>|EmployeeJobPosition query()
 *
 * @mixin Eloquent
 */
class EmployeeJobPosition extends Pivot
{
    public $incrementing = true;

    protected $guarded = [];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }
}
