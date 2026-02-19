/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface DeliveryNote {
    id: number
    slug:string,
    shop_id: string,
    customer_id: number,
    number: number
    type: string
    state: string
    email: string
    created_at: string
    updated_at: string
    phone: number
    number_stocks: string
    number_picks: number
    picker_id: string
    packer_id: string

}
