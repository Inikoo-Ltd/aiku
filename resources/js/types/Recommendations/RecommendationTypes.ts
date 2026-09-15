/**
 * Author: Vika Aqordi
 * Created on 14-09-2026-10h-30m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

export interface InternalRecommendationProduct {
    id: number
    code: string
    name: string
    stock?: number
    price?: number
    rrp?: number
    unit?: string
    units?: number
    url?: string
    canonical_url?: string
    webpage_id?: number
    web_images?: {
        main?: {
            original?: string
        }
    }
}
