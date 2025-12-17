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
    state_icon: StateIcon
    show_in_checkout: boolean
    number_payments: number
    amount_successfully_paid: number
    shop_currency_code: string
}

export type PaymentAccountShopState = 'in_process' | 'active' | 'inactive'

export type StateIcon = {
    tooltip: string
    icon: string | [string, string]
    class?: string
}
