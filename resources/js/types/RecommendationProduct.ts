import type { Image } from '@/types/Image'

export interface RecommendationProduct {
    id: number
    code: string
    name: string
    url: string | null
    stock: number | string
    price: number | string
    units: number
    unit: string | null
    web_images: {
        main?: {
            original?: Image
        }
    } | null
}
