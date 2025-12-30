<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

class CollectionTimeSeriesRecord extends Model
{
    protected $table = 'collection_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}
