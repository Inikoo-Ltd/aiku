/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Mar 2023 14:00:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Location {
    slug: string,
    warehouse_area_slug: string,
    warehouse_slug: string
    organisation_slug: string,
    code: string
    status: string
    stock_value: string
    created_at: string
    updated_at: string
}
