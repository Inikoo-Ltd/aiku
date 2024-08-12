<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Apr 2023 11:32:22 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\SysAdmin\OrganisationAccountingStats
 *
 * @property int $id
 * @property int $organisation_id
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
 * @property int $number_credit_transactions
 * @property int $number_top_ups
 * @property int $number_top_ups_status_in_process
 * @property int $number_top_ups_status_success
 * @property int $number_top_ups_status_fail
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static Builder|OrganisationAccountingStats newModelQuery()
 * @method static Builder|OrganisationAccountingStats newQuery()
 * @method static Builder|OrganisationAccountingStats query()
 * @mixin Eloquent
 */
class OrganisationAccountingStats extends Model
{
    protected $table = 'organisation_accounting_stats';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
