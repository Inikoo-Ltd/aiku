<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 10:42:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentSubsequentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Currency;
use App\Models\Helpers\UniversalSearch;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InCustomer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $payment_service_provider_id
 * @property int $org_payment_service_provider_id
 * @property int $payment_account_id
 * @property int $shop_id
 * @property int $customer_id
 * @property PaymentTypeEnum $type
 * @property string|null $reference
 * @property PaymentStatusEnum $status
 * @property PaymentStateEnum $state
 * @property PaymentSubsequentStatusEnum|null $subsequent_status
 * @property int $currency_id
 * @property numeric $amount
 * @property numeric $grp_amount
 * @property numeric $org_amount
 * @property array<array-key, mixed> $data
 * @property string $date Most relevant date at current state
 * @property string|null $completed_at
 * @property string|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property bool $with_refund
 * @property string|null $source_id
 * @property int|null $original_payment_id Only use when payment refund to original payment
 * @property int|null $payment_account_shop_id
 * @property string|null $api_point_type
 * @property int|null $api_point_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency $currency
 * @property-read Customer $customer
 * @property-read Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Accounting\Invoice> $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Order> $orders
 * @property-read \App\Models\Accounting\OrgPaymentServiceProvider $orgPaymentServiceProvider
 * @property-read Organisation $organisation
 * @property-read \App\Models\Accounting\PaymentAccount $paymentAccount
 * @property-read Shop $shop
 * @property-read \App\Models\Accounting\TopUp|null $topUp
 * @property-read UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Accounting\PaymentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Payment newModelQuery()
 * @method static Builder<static>|Payment newQuery()
 * @method static Builder<static>|Payment onlyTrashed()
 * @method static Builder<static>|Payment query()
 * @method static Builder<static>|Payment withTrashed()
 * @method static Builder<static>|Payment withoutTrashed()
 * @mixin Eloquent
 */
class Payment extends Model implements Auditable
{
    use SoftDeletes;
    use HasUniversalSearch;
    use HasFactory;
    use InCustomer;
    use HasHistory;

    protected $casts = [
        'data'              => 'array',
        'state'             => PaymentStateEnum::class,
        'status'            => PaymentStatusEnum::class,
        'subsequent_status' => PaymentSubsequentStatusEnum::class,
        'type'              => PaymentTypeEnum::class,
        'amount'            => 'decimal:2',
        'grp_amount'        => 'decimal:2',
        'org_amount'        => 'decimal:2',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'accounting',
        ];
    }

    protected array $auditInclude = [
        'reference',
        'status',
        'state',
        'amount',
    ];

    protected static function booted(): void
    {
        static::creating(
            function (Payment $payment) {
                $payment->type = $payment->amount >= 0 ? PaymentTypeEnum::PAYMENT : PaymentTypeEnum::REFUND;
            }
        );
    }


    public function orgPaymentServiceProvider(): BelongsTo
    {
        return $this->belongsTo(OrgPaymentServiceProvider::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }


    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function topUp(): HasOne
    {
        return $this->hasOne(TopUp::class);
    }

    public function invoices(): MorphToMany
    {
        return $this->morphedByMany(Invoice::class, 'model', 'model_has_payments');
    }

    public function orders(): MorphToMany
    {
        return $this->morphedByMany(Order::class, 'model', 'model_has_payments');
    }
}
