<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 Jul 2026 00:36:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DebugWebhooks newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DebugWebhooks newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DebugWebhooks query()
 * @mixin \Eloquent
 */
class DebugWebhooks extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array'
    ];

    protected $attributes = [
        'data' => '{}'
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
