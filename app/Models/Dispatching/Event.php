<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 22:27:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 */

/**
 * App\Models\Dispatching\Event
 *
 * @property-read \App\Models\Dispatching\Shipment|null $shipment
 * @method static Builder<static>|Event newModelQuery()
 * @method static Builder<static>|Event newQuery()
 * @method static Builder<static>|Event onlyTrashed()
 * @method static Builder<static>|Event query()
 * @method static Builder<static>|Event withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Event withoutTrashed()
 * @mixin Eloquent
 */
class Event extends Model
{
    use SoftDeletes;

    use HasFactory;

    protected $casts = [
        'data'   => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::created(
            function (event $event) {
                $event->shipment->update_state();
            }
        );
    }
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

}
