/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Sept 2025 23:21:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Updated: Tue, 09 Sept 2025 23:30:00 Local
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


export interface Shipper {
  id: number
  slug: string
  code: string
  name: string
  trade_as: string | null
  phone: string | null
  website: string | null
  tracking_url: string | null
  api_shipper: string | null
  label: string | null
  type: string
}
