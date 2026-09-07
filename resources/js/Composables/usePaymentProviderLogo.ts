import btree from '@/../art/payment_service_providers/btree.svg'
import cash from '@/../art/payment_service_providers/cash.svg'
import checkout from '@/../art/payment_service_providers/checkout.svg'
import hokodo from '@/../art/payment_service_providers/hokodo.svg'
import pastpay from '@/../art/payment_service_providers/pastpay.svg'
import paypal from '@/../art/payment_service_providers/paypal.svg'
import sofort from '@/../art/payment_service_providers/sofort.svg'
import worldpay from '@/../art/payment_service_providers/worldpay.svg'
import bank from '@/../art/payment_service_providers/bank.svg'
import accounts from '@/../art/payment_service_providers/accounts.svg'
import cond from '@/../art/payment_service_providers/cond.svg'

const logos: Record<string, string> = {
    btree,
    braintree: btree,
    braintree_paypal: btree,
    cash,
    checkout,
    hokodo,
    pastpay,
    paypal,
    sofort,
    worldpay,
    world_pay: worldpay,
    bank,
    accounts,
    account: accounts,
    cond,
    cash_on_delivery: cond,
}

export const paymentProviderLogo = (type?: string | null): string | null => (type && logos[type]) || null
