/**
 * Author: Vika Aqordi
 * Created on: 18-09-2025-10h-57m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

export interface ProductHit {
    attributes: {
        image_link: string
        price: string
        formatted_price: string
        department: string[]
        category: string[]
        product_code: string[]
        stock_qty: string[]
        title: string
        web_url: string[]
        product_id: string
    }
}