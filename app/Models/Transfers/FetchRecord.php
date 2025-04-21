<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 14 Jan 2024 20:25:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Transfers;

use App\Enums\Transfers\FetchRecord\FetchRecordTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Transfers\FetchRecord
 *
 * @property int $id
 * @property int $fetch_id
 * @property FetchRecordTypeEnum $type
 * @property string|null $error_on
 * @property string|null $source_id
 * @property string|null $model_type
 * @property string|null $model_id
 * @property array<array-key, mixed> $model_data
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Transfers\Fetch $fetch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FetchRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FetchRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FetchRecord query()
 * @mixin \Eloquent
 */
class FetchRecord extends Model
{
    protected $casts = [
        'data'       => 'array',
        'model_data' => 'array',
        'type'       => FetchRecordTypeEnum::class,
    ];

    protected $attributes = [
        'data'       => '{}',
        'model_data' => '{}',
    ];

    protected $guarded = [];

    public function fetch(): BelongsTo
    {
        return $this->belongsTo(Fetch::class);
    }


}
