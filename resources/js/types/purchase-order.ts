/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface PurchaseOrder {
    id: number,
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
    agent_name: string | null
    agent_slug: string | null
    org_agent_slug: string | null
    supplier_slug: string | null
    supplier_purchase_orders?: number | null
    agent_order_reference?: string | null
    reference?: string
    state_label?: string
    state_icon?: { tooltip?: string, icon: string | string[], class?: string } | null
    date?: string | null
    number_current_purchase_order_transactions?: number | null
    quantity_ordered?: number | string | null
    org_currency_code?: string | null
    org_total_cost?: number | string | null
    org_net_amount?: number | string | null
    organisation_name?: string | null
    organisation_code?: string | null
    organisation_slug?: string | null
    number_pending_line_answers?: number
    shipments?: {
        reference: string
        state_icon: { tooltip?: string, icon: string, class?: string } | null
        cbm: number | null
        show_route: { name: string, parameters: (string | number)[] } | null
    }[]
}
