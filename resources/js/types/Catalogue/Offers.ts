/**
 * Author: Vika Aqordi
 * Created on 28-01-2026-16h-04m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

import { Image } from '@/types/Image'

export interface OfferProductCategoryLink {
    name: string
    slug: string
    type: 'department' | 'sub_department' | 'family'
}

export interface OfferDiscountStep {
    min_quantity: number
    percentage_off: number
}

export interface OfferProduct {
    id: number
    slug: string
    code: string
    name: string | null
    price: number | null
    image: Image | null
}

export interface OfferSimulation {
    mode: 'quantity' | 'amount'
    quantity: number
    isQuantityExact: boolean
    freeUnits: number
    percentageOff: number
    grossAmount: number
    netAmount: number
    savedAmount: number
    meterCurrent: number
    meterTarget: number
    isReached: boolean
}

export interface OfferGiftData {
    min_order_amount: number | null
    item_quantity: number | null
    quantity: number
    product: OfferProduct | null
}

export interface OfferResource {
    id?: number
    type: 'Amount AND Order Number' | 'Category Ordered' | 'Category Quantity Ordered' | 'GR Amnesty' | 'Category Quantity Ordered Order Interval' | string
    name: string
    code?: string
    label?: string | null
    label_got?: string | null
    information?: string | null
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
        min_order_amount?: number
    }
    trigger_product?: OfferProduct | null
    gift_data?: OfferGiftData | null
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
        steps?: OfferDiscountStep[]
        product_id?: number
        item_quantity?: number
        free_quantity?: number
        quantity?: number
        [key: string]: unknown
    } | null
    state: string | null
    created_at: string | null
    updated_at: string | null
}
