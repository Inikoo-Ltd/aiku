/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Mailshot {

    id: number,
    state:string,
    outbox_id:string,
    data: string,
    created_at: string
    updated_at: string,
    slug: string,
    shop_id?: number,
    webpage_slug?: string,
    webpage_website_slug?: string,


}
