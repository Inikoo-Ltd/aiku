<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 21:40:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\SysAdmin\Organisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Helpers\SerialReference
 *
 * @property int $id
 * @property string $container_type
 * @property int $container_id
 * @property int|null $organisation_id
 * @property SerialReferenceModelEnum $model
 * @property int $serial
 * @property string $format
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Organisation|null $organisation
 *
 * @method static Builder<static>|SerialReference newModelQuery()
 * @method static Builder<static>|SerialReference newQuery()
 * @method static Builder<static>|SerialReference query()
 *
 * @mixin Eloquent
 */
class SerialReference extends Model
{
    protected $casts = [
        'data' => 'array',
        'model' => SerialReferenceModelEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',

    ];

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
