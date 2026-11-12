<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Enums\Accounting\BankConnection\BankConnectionStateEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property BankConnectionProviderEnum $provider
 * @property string $name
 * @property string|null $external_id
 * @property string|null $credentials
 * @property BankConnectionStateEnum $state
 * @property array<string, mixed> $sync_data
 * @property array{auto_pay_from?: string, payment_accounts?: array<string, int>, bank_fee_payment_account_id?: int, fee_ceiling_eur?: float} $settings
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property \Illuminate\Support\Carbon|null $last_error_at
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BankTransaction> $bankTransactions
 * @mixin \Eloquent
 */
class BankConnection extends Model
{
    protected $guarded = [];

    protected $hidden = ['credentials'];

    protected $attributes = [
        'sync_data' => '{}',
        'settings'  => '{}',
    ];

    protected function casts(): array
    {
        return [
            'provider'       => BankConnectionProviderEnum::class,
            'state'          => BankConnectionStateEnum::class,
            'credentials'    => 'encrypted',
            'sync_data'      => 'array',
            'settings'       => 'array',
            'last_synced_at' => 'datetime',
            'last_error_at'  => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
