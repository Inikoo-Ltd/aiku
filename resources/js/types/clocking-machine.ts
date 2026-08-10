/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { routeType } from "@/types/route"

export interface ClockingMachine {
    id?: number,
    slug: string,
    code: string,
    name?: string,
    type?: string,
    workplace_slug: string,
    organisation_slug: string,
    org_id: number,
    workplace_id: number,
    kiosk_url?: string | null,
    kiosk_enabled?: boolean | null,
    delete_route?: routeType,
}
