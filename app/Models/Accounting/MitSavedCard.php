<?php

namespace App\Models\Accounting;

use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int $payment_account_shop_id
 * @property string|null $token
 * @property string|null $last_four_digits
 * @property string|null $card_type Visa, Mastercard, etc
 * @property \Illuminate\Support\Carbon|null $expires_at Card expiration date
 * @property string|null $label User defined label
 * @property string|null $state
 * @property int $priority
 * @property array<array-key, mixed> $data
 * @property string $ulid
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $failure_status
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Accounting\PaymentAccountShop $paymentAccountShop
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitSavedCard newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitSavedCard newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitSavedCard query()
 * @mixin \Eloquent
 */
class MitSavedCard extends Model
{
    use InCustomer;

    protected $casts = [
        'data' => 'json',
        'expires_at' => 'date',
        'processed_at' => 'datetime',
    ];

    protected $attributes = [
        'data'         => '{}',
    ];

    protected $guarded = [];

    public function paymentAccountShop(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountShop::class);
    }

}
