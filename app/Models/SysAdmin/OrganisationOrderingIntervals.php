<?php

/*
 * author Arya Permana - Kirin
 * created on 17-12-2024-13h-39m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationOrderingIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationOrderingIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationOrderingIntervals query()
 * @mixin \Eloquent
 */
class OrganisationOrderingIntervals extends Model
{
    protected $table = 'organisation_ordering_intervals';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
