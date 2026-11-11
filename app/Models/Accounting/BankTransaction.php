<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a bank account as the bank reports it, whichever bank it comes from.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $bank_connection_id
 * @property string $provider_transaction_id
 * @property string|null $provider_type
 * @property BankTransactionDirectionEnum $direction
 * @property string $amount
 * @property int $currency_id
 * @property BankTransactionStatusEnum $status
 * @property string|null $account_reference
 * @property string|null $counterparty_name
 * @property string|null $counterparty_account
 * @property string|null $reference
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $booked_at
 * @property \Illuminate\Support\Carbon|null $provider_updated_at
 * @property array<string, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read BankConnection $bankConnection
 * @property-read Currency $currency
 * @property-read Organisation $organisation
 * @mixin \Eloquent
 */
class BankTransaction extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'data' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'direction'           => BankTransactionDirectionEnum::class,
            'status'              => BankTransactionStatusEnum::class,
            'data'                => 'array',
            'booked_at'           => 'datetime',
            'provider_updated_at' => 'datetime',
        ];
    }

    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
