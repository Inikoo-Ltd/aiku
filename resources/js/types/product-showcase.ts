/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import type { Images } from "@/types/Images"

export type CountryInfo = {
  name: string
  code: string
}


export interface PictureSources {
  original?: string
  webp?: string
  [key: string]: unknown
}

export interface ProductShowcase {
  id: number
  slug: string
  image_id: number | null
  code: string
  name: string
  units: string | number | null
  unit: string | null
  rrp: number | string | null
  barcode: string | null
  price: number | string | null
  currency_code: string
  description: string | null
  description_title: string | null
  description_extra: string | null
  state: string
  created_at: string
  updated_at: string
  images: Images[]
  image_thumbnail: PictureSources | null
  stock: number | null
  marketing_ingredients: string | null
  marketing_dimensions: string | null
  marketing_weight: number | string | null
  gross_weight: number | string | null
  is_name_reviewed: boolean
  is_description_title_reviewed: boolean
  is_description_reviewed: boolean
  is_description_extra_reviewed: boolean
  cpnp_number: string | null
  ufi_number: string | null
  scpn_number: string | null
  country_of_origin: CountryInfo | Record<string, never> | null
  picking_factor: Array<{
    org_stock_id: number
    org_stock_code: string
    note: string | null
    picking_factor: unknown
  }>
  tariff_code: string | null
  duty_rate: number | null
  hts_user_id: number | null
  un_number: string | null
  un_class: string | null
  packing_group: string | null
  proper_shipping_name: string | null

  hazard_identification_number: string | null
  gpsr_manufacturer: string | null
  gpsr_eu_responsible: string | null
  gpsr_warnings: string | null
  gpsr_manual: string | null
  gpsr_class_category_danger: string | null
  gpsr_product_languages: string | null

}
