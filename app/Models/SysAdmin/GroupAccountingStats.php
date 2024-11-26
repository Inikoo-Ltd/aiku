<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 11:39:03 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\GroupAccountingStats
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_payment_service_providers
 * @property int $number_payment_service_providers_type_account
 * @property int $number_payment_service_providers_type_cash
 * @property int $number_payment_service_providers_type_bank
 * @property int $number_payment_service_providers_type_electronic_payment_servic
 * @property int $number_payment_service_providers_type_electronic_banking_e_paym
 * @property int $number_payment_service_providers_type_cash_on_delivery
 * @property int $number_payment_service_providers_type_buy_now_pay_later
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
 * @property int $number_payment_accounts_type_PASTPAY
 * @property int $number_payment_accounts_type_cash_on_delivery
 * @property int $number_payment_accounts_type_xendit
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
 * @property string $org_amount organisation currency, amount_successfully_paid-amount_returned
 * @property string $org_amount_successfully_paid
 * @property string $org_amount_refunded
 * @property string $group_amount Group currency, amount_successfully_paid-amount_returned
 * @property string $group_amount_successfully_paid
 * @property string $group_amount_refunded
 * @property int $number_invoices
 * @property int $number_invoices_type_invoice
 * @property int $number_invoices_type_refund
 * @property string|null $last_invoiced_at
 * @property int $number_credit_transactions
 * @property string $customer_balances_grp_amount
 * @property string $customer_positive_balances_grp_amount
 * @property string $customer_negative_balances_grp_amount
 * @property int $number_top_ups
 * @property int $number_top_ups_status_in_process
 * @property int $number_top_ups_status_success
 * @property int $number_top_ups_status_fail
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupAccountingStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupAccountingStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupAccountingStats query()
 * @mixin \Eloquent
 */
class GroupAccountingStats extends Model
{
    protected $table = 'group_accounting_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
