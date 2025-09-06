<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:36:40 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $fraction_digits
 * @property bool $status
 * @property bool $store_historic_data
 * @property \Illuminate\Support\Carbon|null $historic_data_since
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\CurrencyExchange> $exchanges
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency query()
 * @mixin \Eloquent
 */
class Currency extends Model
{
    protected $casts = [
        'data'               => 'array',
        'historic_data_since' => 'datetime'
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function exchanges(): HasMany
    {
        return $this->hasMany(CurrencyExchange::class);
    }
}
