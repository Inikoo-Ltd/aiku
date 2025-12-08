<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 10:59:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Helpers\FetchCurrencyExchange
 *
 * @property int $id
 * @property int $currency_id
 * @property string $exchange
 * @property string $date
 * @property string|null $source F:Frankfurter, CB:currencyBeacon, M:manual
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Helpers\Currency $currency
 *
 * @method static Builder<static>|CurrencyExchange newModelQuery()
 * @method static Builder<static>|CurrencyExchange newQuery()
 * @method static Builder<static>|CurrencyExchange query()
 *
 * @mixin Eloquent
 */
class CurrencyExchange extends Model
{
    protected $guarded = [];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
