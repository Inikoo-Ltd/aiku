<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Sep 2023 18:45:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\ExcelUploadRecord
 *
 * @property int $id
 * @property int $upload_id
 * @property int|null $row_number
 * @property array<array-key, mixed> $values
 * @property array<array-key, mixed> $errors
 * @property string|null $fail_column
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \App\Models\Helpers\Upload $excel
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRecord query()
 *
 * @mixin \Eloquent
 */
class UploadRecord extends Model
{
    protected $casts = [
        'values' => 'array',
        'errors' => 'array',

    ];

    protected $attributes = [
        'values' => '{}',
        'errors' => '{}',
    ];

    protected $guarded = [];

    public function excel(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'upload_id');
    }
}
