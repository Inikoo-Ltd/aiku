/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Supplier {
    id: number,
    type: string,
    agent_slug: string,
    slug: string,
    code: string
    owner_type: string
    name: string
    company_name: string
    created_at: string
    updated_at: string
    contact_name: string
    email: string
    phone: string

}

export interface SupplierOptionFields {
    code: string
    nickname: string | null
    company_name: string | null
    contact_name: string | null
    product_codes?: string
}

export const supplierOptionFilterFields = ["code", "nickname", "company_name", "contact_name", "product_codes"]
