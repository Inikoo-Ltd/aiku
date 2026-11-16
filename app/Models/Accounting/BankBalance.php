<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a bank connection held in one currency on one day: the last reading of the day wins, so a trend builds up
 * one row a day. Balances of the same currency are added together.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $bank_connection_id
 * @property int $currency_id
 * @property \Illuminate\Support\Carbon $date
 * @property numeric $amount
 * @property numeric $reserved_amount
 * @property \Illuminate\Support\Carbon $taken_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read BankConnection $bankConnection
 * @property-read Organisation $organisation
 * @property-read Currency $currency
 * @mixin \Eloquent
 */
class BankBalance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'     => 'date',
            'taken_at' => 'datetime',
        ];
    }

    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
