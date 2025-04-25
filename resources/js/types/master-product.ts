/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 15:11:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

export interface MasterProduct {
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
    master_family_slug: string
    master_family_code: string
    master_family_name: string
}
