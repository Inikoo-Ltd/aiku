<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $collection_time_series_id
 * @property string $sales
 * @property string $sales_org_currency
 * @property string $sales_grp_currency
 * @property int $invoices
 * @property int $refunds
 * @property int $orders
 * @property int $delivery_notes
 * @property int $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\CollectionTimeSeries|null $timeSeries
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionTimeSeriesRecord query()
 * @mixin \Eloquent
 */
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
