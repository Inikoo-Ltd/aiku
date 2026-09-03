

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Jun 2023 15:23:52 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface JobPosition {
    slug: string
    name: string,
    code?: string,
    number_employees_currently_working?: number,
    share?: string | null,
    organisation_name?: string | null,
    organisation_slug?: string | null,
}
