/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Collection {
    slug:string,
    shop_slug: string,
    shop_code: string,
    shop_name: string,
    department_slug: string,
    department_code: string,
    department_name: string,
    state: string
    code: string
    name: string
    description: string
    created_at: string
    updated_at: string
    webpage_slug: string,
    webpage_url: string,
    webpage_state: string,
    website_slug: string,
}
