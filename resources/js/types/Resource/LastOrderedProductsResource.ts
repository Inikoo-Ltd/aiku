/**
 * Author: Vika Aqordi
 * Created on 07-10-2025-11h-40m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { Image } from "../Image"

export interface LastOrderedProduct {
    id: number
    slug: string
    asset_id: number
    historic_id: number
    code: string
    name: string
    image_thumbnail: Image
    image: Image
    state: string
    available_quantity: number
    price: number
    submitted_at: string
    customer_contact_name: string
    customer_name: string
    customer_country_code: string
}