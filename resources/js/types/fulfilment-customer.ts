/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface FulfilmentCustomer {
    slug: string
    reference: string
    name: string
    contact_name: string
    company_name?: string
    email: string
    phone: string
    created_at: string
    updated_at: string
    shop: string
    shop_slug: string
    shop_code: string
    number_current_customer_clients: number
}
