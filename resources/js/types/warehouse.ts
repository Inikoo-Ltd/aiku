/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Mar 2023 16:49:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Warehouse {
    id: string,
    slug: string,
    code: string,
    name: string,
    settings: string,
    created_at: string,
    updated_at: string,
}

export interface DeliveryNote {
    id: number
    slug: string
    reference: string
    date: string
    state: string
    type: string
    status: string
    weight: string
    created_at: string
    updated_at: string
    shop_slug: string | null
    customer_slug: string | null
    customer_name: string | null
    number_items: number | null
    is_premium_dispatch: boolean
}
