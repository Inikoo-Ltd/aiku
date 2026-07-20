/*
 * Author: Raul Perusquia <raul@inikoo.com>  
 * Created: Tue, 16 Dec 2025 19:55:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

export interface PaymentAccountShop {
    id: number
    shop_id: number
    shop_code: string
    shop_name: string
    shop_slug: string
    payment_account_code: string
    payment_account_name: string
    payment_account_slug: string
    activated_at: string | null
    state: PaymentAccountShopState
    state_label?: string
    state_icon: StateIcon
    show_in_checkout: boolean
    checkout_display_position?: number | null
    number_payments: number
    amount_successfully_paid: number
    shop_currency_code: string
    type?: string
    pastpay?: {
        tax_number: string | null
        credit_terms: PaymentAccountShopChargeOption[]
        invoice_footer: string | null
        setup_checklist: { label: string; done: boolean }[]
    }
}

export interface PaymentAccountShopChargeOption {
    days: number
    charge: string
}

export type PaymentAccountShopState = 'in_process' | 'active' | 'inactive'

export type StateIcon = {
    tooltip: string
    icon: string | [string, string]
    class?: string
}
