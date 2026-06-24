/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Mar 2023 20:28:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface PaymentAccount {
    slug: string,
    shop_slug?: string,
    payment_service_provider_slug: string
    org_payment_service_provider_slug: string
    number_payments: number
    code: string
    created_at: string
    updated_at: string

}
