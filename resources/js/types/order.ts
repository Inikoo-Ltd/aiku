/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Order {

    slug:string,
    number:number,
    customer_reference: string,
    type: string,
    state: string
    date: string
    created_at: string
    updated_at: string
    shop: string
    shop_slug: string
    customer_slug: string
    organisation_slug: string

}
