<?php
/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-18h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Inventory;

use App\Actions\Utils\Abbreviate;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class PickingSession extends Model
{
    use HasFactory;
    use HasSlug;

    protected $guarded = [];

    protected $casts = [
        'state'                  => PickingSessionStateEnum::class,
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run($this->reference, digits: true, maximumLength: 4);
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }
}
