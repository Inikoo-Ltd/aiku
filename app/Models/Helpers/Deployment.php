<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Oct 2023 21:39:19 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Helpers\Deployment
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string|null $scope
 * @property string|null $publisher_type
 * @property int|null $publisher_id
 * @property int|null $snapshot_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $model
 * @property-read \App\Models\Helpers\Snapshot|null $snapshot
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Deployment query()
 *
 * @mixin \Eloquent
 */
class Deployment extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s P';

    protected array $dates = ['published_at'];

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }
}
