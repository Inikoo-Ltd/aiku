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
 * @property string $frequency
 * @property string|null $sales_external
 * @property string|null $sales_org_currency_external
 * @property string|null $sales_grp_currency_external
 * @property string|null $sales_internal
 * @property string|null $sales_org_currency_internal
 * @property string|null $sales_grp_currency_internal
 * @property int|null $invoices
 * @property int|null $refunds
 * @property int|null $orders
 * @property int|null $delivery_notes
 * @property int|null $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
