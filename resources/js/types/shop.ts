/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Shop {
    code: string
    id:string
    name: string
    sales: {}
    slug: string
    state: string
    domain?: string
    created_at?: string
    updated_at?: string
    organisation_slug: string

}
