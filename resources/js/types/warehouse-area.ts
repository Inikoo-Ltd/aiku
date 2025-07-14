/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Mar 2023 16:49:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface WarehouseArea {
    slug: string,
    warehouse_slug: string,
    code: string,
    name: string,
    picking_position?: number,
    unit_quantity: string,
    value: string,
    created_at: string,
    updated_at: string,
}
