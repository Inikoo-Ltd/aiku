/**
 * Author: Vika Aqordi
 * Created on 28-01-2026-16h-04m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

export interface OfferResource {
    type: 'Amount AND Order Number' | 'Category Ordered' | 'Category Quantity Ordered'
    name: string
    label?: string
    state: string
    status: string
    trigger_data: {
        min_amount: number
        order_number: number
        item_quantity: number
    }
    duration: string  // 'permanent'
    created_at: string
    end_at: string
    triggers_labels: string[]
    allowances: {}[]
    data_allowance_signature: {
        percentage_off: string
    }
    max_percentage_discount?: number

}