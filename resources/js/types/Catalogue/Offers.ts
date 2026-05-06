/**
 * Author: Vika Aqordi
 * Created on 28-01-2026-16h-04m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

export interface OfferProductCategoryLink {
    name: string
    slug: string
    type: 'department' | 'sub_department' | 'family'
}

export interface OfferResource {
    id?: number
    type: 'Amount AND Order Number' | 'Category Ordered' | 'Category Quantity Ordered' | 'GR Amnesty' | 'Category Quantity Ordered Order Interval' | string
    name: string
    label?: string | null
    state: string
    status: boolean | string
    duration?: string | null
    start_at?: string | null
    end_at?: string | null
    trigger_type?: string | null
    trigger_data: {
        min_amount?: number
        order_number?: number
        item_quantity?: number
        item_amount?: number
        interval?: number
    }
    percentage_off?: number
    created_at: string
    updated_at?: string
    triggers_labels: string[]
    allowances: {}[]
    data_allowance_signature: {
        percentage_off: string
        product_category?: OfferProductCategoryLink | null
    }
    max_percentage_discount?: number
    offer_campaign?: {
        id: number
        slug: string
        name: string
    } | null
}

export interface OfferAllowanceResource {
    offer_campaign_id: number | null
    slug: string
    code: string | null
    name: string | null
    data: {
        percentage_off?: number
        [key: string]: unknown
    } | null
    state: string | null
    created_at: string | null
    updated_at: string | null
}
