/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */
import { Address } from '@/types/PureComponent/Address'
import { Icon } from '@/types/Utils/Icon'

export interface Invoice {
    id:number,
    slug:string,
    number: string,
    customer_id: string,
    order_id: string
    type: string
    currency_id: string
    total: number
    created_at: string
    updated_at: string
    net: number
    payment: number
    in_process: boolean
    organisation_slug: string
    shop_slug: string
    shop_id: string
    customer_slug: string
}

export interface InvoiceResource {
    address: Address
    created_at: string
    date: string
    net_amount: string
    number: string
    paid_at: string
    slug: string
    tax_liability_at: string
    total_amount: string
    in_process : boolean
    type: {
        icon: Icon
        label: string
    }
    currency_code: string
    updated_at: string
}
