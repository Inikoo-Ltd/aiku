/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { route } from "ziggy-js"

type Query = Record<string, string | number | null | undefined>

/**
 * A link to a tab of the websites dashboard keeping the selected websites and days; a query value of
 * null drops that parameter. Tables sort with `<tab>_sort`.
 */
export const edgeDrillHref = (base: { website?: string | null; days?: number | string | null }, tab: string, query: Query = {}): string => {
    const params: Record<string, string | number> = { tab }
    for (const [key, value] of Object.entries({ website: base.website, days: base.days, ...query })) {
        if (value !== null && value !== undefined && value !== "" && !(key === "website" && value === "all")) {
            params[key] = value
        }
    }

    return route("grp.websites.index", params)
}
