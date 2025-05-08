<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:30:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Traits\HasHistory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

/**
 *
 *
 * @property int $id
 * @property int $shop_id
 * @property int $payment_account_id
 * @property int $currency_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property PaymentAccountShopStateEnum $state
 * @property PaymentAccountTypeEnum|null $type
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $last_activated_at
 * @property \Illuminate\Support\Carbon|null $inactive_at
 * @property bool $show_in_checkout
 * @property int $checkout_display_position for the order in which will be shown in checkout UI
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency $currency
 * @property-read \App\Models\Accounting\PaymentAccount $paymentAccount
 * @property-read Shop $shop
 * @property-read \App\Models\Accounting\PaymentAccountShopStats|null $stats
 * @method static Builder<static>|PaymentAccountShop newModelQuery()
 * @method static Builder<static>|PaymentAccountShop newQuery()
 * @method static Builder<static>|PaymentAccountShop query()
 * @mixin Eloquent
 */
class PaymentAccountShop extends Model implements Auditable
{
    use HasHistory;

    protected $table = 'payment_account_shop';

    protected $casts = [
        'data'  => 'array',
        'state' => PaymentAccountShopStateEnum::class,
        'type'  => PaymentAccountTypeEnum::class,
        'activated_at' => 'datetime',
        'last_activated_at' => 'datetime',
        'inactive_at' => 'datetime',


    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'state',
            'type'
        ];
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PaymentAccountShopStats::class);
    }

    public function getCredentials(): array
    {
        $credentials = [];

        if ($this->type == PaymentAccountTypeEnum::CHECKOUT) {
            $credentials = $this->getCheckoutComCredentials();
        }

        return $credentials;

    }

    public function getCheckoutComCredentials(): array
    {
        if (app()->environment('production')) {
            return [
                Arr::get($this->paymentAccount->data, 'credentials.public_key'),
                Arr::get($this->paymentAccount->data, 'credentials.secret_key'),
            ];
        } else {
            return [
                config('app.sandbox.checkout_com.public_key'),
                config('app.sandbox.checkout_com.secret_key'),
            ];
        }
    }

    public function getCheckoutComChannel(): array
    {
        if (app()->environment('production')) {
            return  Arr::get($this->data, 'credentials.payment_channel');
        } else {
            return config('app.sandbox.checkout_com.payment_channel');
        }
    }




}
