/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Product {
    slug:string,
    owner_type: string,
    shop_slug:string
    organisation_slug:string
    state: string
    code: string
    name: string
    description: string
    created_at: string
    updated_at: string
    units: number
    price: number

}
