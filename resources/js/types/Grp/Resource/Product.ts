/**
 * Author: Vika Aqordi
 * Created on 17-11-2025-11h-38m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { Image } from "@/types/Image"

export interface ProductResource {
    id: number | string
    slug: string
    image_id: number | string | null
    code: string
    name: string
    units: number | string
    unit: string
    rrp: number
    barcode: string
    price: number
    currency_code: string
    description: string
    description_title: string
    description_extra: string
    state: string | number
    created_at: string
    updated_at: string
    images: Image[] // Replace 'any' with your ImageResource type if available
    image_thumbnail: Image
    stock: number
    marketing_dimensions: string
    marketing_ingredients: string
    marketing_weight: string
    gross_weight: string
    is_name_reviewed: boolean
    is_description_title_reviewed: boolean
    is_description_reviewed: boolean
    is_description_extra_reviewed: boolean
    cpnp_number: string
    ufi_number: string
    scpn_number: string
    picking_factor: number
    country_of_origin: string
    tariff_code: string
    duty_rate: number
    hts_us: string
    un_number: string
    un_class: string
    packing_group: string
    proper_shipping_name: string
    hazard_identification_number: string
    
    gpsr_manufacturer: string
    gpsr_eu_responsible: string
    gpsr_warnings: string
    gpsr_manual: string
    gpsr_class_category_danger: string
    gpsr_product_languages: string[]

    rrp_per_unit: number
    margin: number
    profit: number
    profit_per_unit: number
    price_per_unit: number
}