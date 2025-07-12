/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 08:41:51 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

export interface Customer {
    id : number
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
