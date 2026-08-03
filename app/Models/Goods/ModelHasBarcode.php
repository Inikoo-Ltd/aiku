<?php

/*
 * Author Louis Perez
 * Created on 19-06-2026-11h-18m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $type
 * @property bool $status
 * @property int $barcode_id
 * @property string $model_type
 * @property int $model_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $withdrawn_at
 * @property-read Model|\Eloquent $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasBarcode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasBarcode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasBarcode query()
 * @mixin \Eloquent
 */
class ModelHasBarcode extends Model
{
    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
