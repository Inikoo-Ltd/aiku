<?php

/*
 * author Arya Permana - Kirin
 * created on 21-11-2024-10h-49m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $aiku_section_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Analytics\AikuSection $aikuSection
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuSectionStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuSectionStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuSectionStats query()
 * @mixin \Eloquent
 */
class AikuSectionStats extends Model
{
    protected $table = 'aiku_section_stats';

    protected $guarded = [
    ];

    public function aikuSection(): BelongsTo
    {
        return $this->belongsTo(AikuSection::class);
    }
}
