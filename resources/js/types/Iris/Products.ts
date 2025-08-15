/**
 * Author: Vika Aqordi
 * Created on: 13-08-2025-11h-21m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/
import { Image as ImageTS } from '@/types/Image'

export interface ProductResource {
    id: number
    name: string
    code: string
    image?: {
        source: ImageTS
    }
    rpp?: number
    unit: string
    stock: number
    rating: number
    price: number
    url: string | null
    units: number
    bestseller?: boolean
    is_favourite?: boolean
    top_seller: number | null
    web_images: {
        main: {
            original: ImageTS
            gallery: ImageTS
        }
    }
    quantity_ordered: number
    quantity_ordered_new: number
    transaction_id: number | null
}