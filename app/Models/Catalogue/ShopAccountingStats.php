<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Feb 2023 23:45:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Catalogue\ShopAccountingStats
 *
 * @property int $id
 * @property int $shop_id
 * @property int $number_org_payment_service_providers
 * @property int $number_org_payment_service_providers_type_account
 * @property int $number_org_payment_service_providers_type_cash
 * @property int $number_org_payment_service_providers_type_bank
 * @property int $number_org_payment_service_providers_type_electronic_payment_se
 * @property int $number_org_payment_service_providers_type_electronic_banking_e_
 * @property int $number_org_payment_service_providers_type_cash_on_delivery
 * @property int $number_org_payment_service_providers_type_buy_now_pay_later
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
 * @property string $amount_paid_balance amount_successfully_paid-amount_returned
 * @property string $amount_successfully_paid
 * @property string $amount_refunded
 * @property string $org_amount_paid_balance organisation currency, amount_successfully_paid-amount_returned
 * @property string $org_amount_successfully_paid
 * @property string $org_amount_refunded
 * @property string $grp_amount_paid_balance Group currency, amount_successfully_paid-amount_returned
 * @property string $grp_amount_successfully_paid
 * @property string $grp_amount_refunded
 * @property int $number_credit_transactions
 * @property int $number_customers_with_balances
 * @property int $number_customers_with_positive_balances
 * @property int $number_customers_with_negative_balances
 * @property int $number_top_ups
 * @property int $number_top_ups_status_in_process
 * @property int $number_top_ups_status_success
 * @property int $number_top_ups_status_fail
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\Shop $shop
 *
 * @method static Builder<static>|ShopAccountingStats newModelQuery()
 * @method static Builder<static>|ShopAccountingStats newQuery()
 * @method static Builder<static>|ShopAccountingStats query()
 *
 * @mixin Eloquent
 */
class ShopAccountingStats extends Model
{
    protected $table = 'shop_accounting_stats';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
