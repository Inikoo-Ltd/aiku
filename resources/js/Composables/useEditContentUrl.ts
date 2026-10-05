/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Mon, 05 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

/**
 * The ticket or task page opens its subject and description editor straight away when the address carries this flag.
 */
export const editContentUrl = (pageUrl: string): string => {
    const url = new URL(pageUrl, window.location.origin)
    url.searchParams.set("edit", "content")
    return url.toString()
}
