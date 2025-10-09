<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Apr 2024 20:35:54 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Accounting\OrgPaymentServiceProviderStats
 *
 * @property int $id
 * @property int $org_payment_service_provider_id
 * @property int $number_payment_accounts
 * @property int $number_payment_accounts_type_paypal
 * @property int $number_payment_accounts_type_world_pay
 * @property int $number_payment_accounts_type_bank
 * @property int $number_payment_accounts_type_sofort
 * @property int $number_payment_accounts_type_cash
 * @property int $number_payment_accounts_type_account
 * @property int $number_payment_accounts_type_braintree
 * @property int $number_payment_accounts_type_braintree_paypal
 * @property int $number_payment_accounts_type_checkout
 * @property int $number_payment_accounts_type_hokodo
 * @property int $number_payment_accounts_type_pastpay
 * @property int $number_payment_accounts_type_cash_on_delivery
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
 * @property string $org_amount_paid_balance organisation currency, amount_successfully_paid-amount_returned
 * @property string $org_amount_successfully_paid
 * @property string $org_amount_refunded
 * @property string $grp_amount_paid_balance Group currency, amount_successfully_paid-amount_returned
 * @property string $grp_amount_successfully_paid
 * @property string $grp_amount_refunded
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Accounting\OrgPaymentServiceProvider $orgPaymentServiceProvider
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPaymentServiceProviderStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPaymentServiceProviderStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPaymentServiceProviderStats query()
 * @mixin \Eloquent
 */
class OrgPaymentServiceProviderStats extends Model
{
    protected $table = 'org_payment_service_provider_stats';

    protected $guarded = [];

    public function orgPaymentServiceProvider(): BelongsTo
    {
        return $this->belongsTo(OrgPaymentServiceProvider::class);
    }
}
