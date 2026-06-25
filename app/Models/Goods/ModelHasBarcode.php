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

class ModelHasBarcode extends Model
{
    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
