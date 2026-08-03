/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface SupplierProduct {
    composition:string,
    slug: string,
    agent_slug: string,
    supplier_slug: string,
    id: number,
    current_historic_supplier_product_id: string,
    supplier_id: number
    agent_id: number
    state: string
    stock_quantity_status: string
    created_at: string
    updated_at: string
    code: string
    name: string
    description: string
    units_per_pack: number
    units_per_carton: string

}
