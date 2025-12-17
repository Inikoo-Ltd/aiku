/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface PurchaseOrder {
    slug: string,
    provider_id: string,
    provider_type: string,
    number: string
    state: string
    status: string
    created_at: string
    updated_at: string
    parent_type: string
    parent_name: string
    parent_slug: string
    agent_slug: string | null
    supplier_slug: string | null
}
