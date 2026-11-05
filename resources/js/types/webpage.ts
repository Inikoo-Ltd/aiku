/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface Webpage {
    id:string,
    slug: string,
    code: string
    type: string
    created_at: string
    updated_at: string
    published_at?: string | null
    last_edited_by?: string | null

}
