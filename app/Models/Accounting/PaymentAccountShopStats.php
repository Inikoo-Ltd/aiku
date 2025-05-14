<?php

/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Accounting;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $payment_account_shop_id
 * @property int $number_payments
 * @property int $number_payments_type_payment
 * @property int $number_payments_type_refund
 * @property int $number_payments_state_in_process
 * @property int $number_payments_state_approving
 * @property int $number_payments_state_completed
 * @property int $number_payments_state_cancelled
 * @property int $number_payments_state_error
 * @property int $number_payments_state_declined
 * @property int $number_payments_type_payment_state_in_process
 * @property int $number_payments_type_payment_state_approving
 * @property int $number_payments_type_payment_state_completed
 * @property int $number_payments_type_payment_state_cancelled
 * @property int $number_payments_type_payment_state_error
 * @property int $number_payments_type_payment_state_declined
 * @property int $number_payments_type_refund_state_in_process
 * @property int $number_payments_type_refund_state_approving
 * @property int $number_payments_type_refund_state_completed
 * @property int $number_payments_type_refund_state_cancelled
 * @property int $number_payments_type_refund_state_error
 * @property int $number_payments_type_refund_state_declined
 * @property string $amount_paid_balance amount_successfully_paid-amount_returned
 * @property string $amount_successfully_paid
 * @property string $amount_refunded
 * @property string $org_amount_paid_balance organisation currency, amount_successfully_paid-amount_returned
 * @property string $org_amount_successfully_paid
 * @property string $org_amount_refunded
 * @property string $grp_amount_paid_balance Group currency, amount_successfully_paid-amount_returned
 * @property string $grp_amount_successfully_paid
 * @property string $grp_amount_refunded
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_customers Number distinct customer with successful payments
 * @property-read \App\Models\Accounting\PaymentAccountShop $paymentAccountShop
 * @method static Builder<static>|PaymentAccountShopStats newModelQuery()
 * @method static Builder<static>|PaymentAccountShopStats newQuery()
 * @method static Builder<static>|PaymentAccountShopStats query()
 * @mixin Eloquent
 */
class PaymentAccountShopStats extends Model
{
    protected $table = 'payment_account_shop_stats';

    protected $guarded = [];

    public function paymentAccountShop(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountShop::class);
    }
}
