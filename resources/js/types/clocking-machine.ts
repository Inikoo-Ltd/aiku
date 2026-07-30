/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface ClockingMachine {
    slug: string,
    code: string,
    name?: string,
    type?: string,
    workplace_slug: string,
    organisation_slug: string,
    org_id: number,
    workplace_id: number,
    kiosk_url?: string | null,
}
