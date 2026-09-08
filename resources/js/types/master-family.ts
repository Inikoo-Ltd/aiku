/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 15:11:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

export interface OfferFreshness {
    value: string
    label: string
    tooltip: string
    class: string
    text_class: string
}

export interface MasterFamilyLastOffer {
    shop_code: string
    shop_name: string
    shop_slug: string
    organisation_slug: string
    offer_slug: string
    offer_name: string
    offer_state: string
    start_at: string | null
    end_at: string | null
    freshness: OfferFreshness
}

export interface MasterFamily {
    id: number
    slug: string
    code: string,
    name: string,
    families: number
    products: number
    master_shop_slug: string
    master_shop_code: string
    master_shop_name: string
    master_department_slug: string
    master_department_code: string
    master_department_name: string
    master_sub_department_slug : string
    master_sub_department_code: string
    master_sub_department_name: string
    last_offers?: MasterFamilyLastOffer[]
    offers_freshness?: OfferFreshness
}
