/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Mar 2023 14:00:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Updated: Sat, 06 Sep 2025 15:01:00 Local
 * Copyright (c) 2023-2025, Raul A Perusquia Flores
 */

// This interface mirrors App\Http\Resources\CRM\CustomerSalesChannelsResource
// Keep fields aligned with the resource payload keys.
export interface CustomerSalesChannel {
  slug: string
  id: number
  reference: string | null
  name: string | null
  number_portfolios: number
  number_portfolio_broken: number
  number_clients: number
  number_customer_clients: number
  number_orders: number
  type: string
  status: string
  total_amount: number | string | null
  platform_code: string
  platform_name: string
  platform_image: string

  can_connect_to_platform: boolean
  exist_in_platform: boolean
  platform_status: boolean

  customer_company_name: string
  customer_slug: string
  customer_id: number
}
