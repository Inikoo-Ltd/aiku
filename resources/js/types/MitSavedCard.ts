/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 May 2025 10:48:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


export interface MitSavedCard
{
  id: number
  token: string
  last_four_digits: string
  card_type: string
  expires_at: string
  processed_at: string
  priority: number
  state: string
  label: null | string
  created_at: string
  updated_at: string
  failure_status: string
}