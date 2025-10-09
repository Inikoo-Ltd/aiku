/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 02:51:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

import type { Icon, StateIcon } from '@/types/Utils/Icon'

export interface OrgStock {
    id: number
    slug: string
    code: string
    state: Icon | StateIcon
    name: string
    quantity: number
    unit_value: number
    number_locations: number
    quantity_locations: number
    family_slug: string | null
    family_code: string | null
    discontinued_in_organisation_at: string | null
    organisation_name: string
    organisation_slug: string
    warehouse_slug: string
}
