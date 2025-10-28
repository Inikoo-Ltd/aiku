/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Mar 2023 20:28:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface PaymentAccountShops {
    id: number,
    shop_currency_code: string,
    shop_id: number,
    shop_code: string,
    shop_name: string,
    shop_slug: string,
    payment_account_slug: string,
    payment_account_code: string,
    payment_account_name: string,
    activated_at: string | null,
    state: string,
    show_in_checkout: boolean,
    number_payments: number,
    amount_successfully_paid: string
}
