import { Image as ImageTS } from '@/types/Image'

export interface BasketBonusStockProduct {
    id: number
    code: string
    name: string
    units: number
    quantity: number
    price: string | number
    rrp: string | number | null
    web_image_thumbnail: ImageTS | null
    canonical_url: string | null
}

export interface BasketBonusStock {
    offer_id: number
    label: string
    family_code: string | null
    family_name: string | null
    current: number
    target: number
    is_same_product?: boolean
    is_reached: boolean
    is_in_basket: boolean
    quantity: number
    products: BasketBonusStockProduct[]
}
