/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Stock {

    slug:string,
    code:string,
    owner_type: string,
    composition: string,
    state: string
    quantity_status: string
    created_at: string
    updated_at: string
    shop: string
    shop_slug: string

    quantity: number

    units_per_pack: number
    units_per_carton: number

}
