<?php

/*
 * author Arya Permana - Kirin
 * created on 17-12-2024-15h-31m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupOrderingIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupOrderingIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupOrderingIntervals query()
 * @mixin \Eloquent
 */
class GroupOrderingIntervals extends Model
{
    protected $table = 'group_ordering_intervals';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
