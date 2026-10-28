/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

// "My shops" are the shops an agent picked in Profile › Alerts to be alerted for, among those
// whose inbox they can open; before they pick, every shop they can open is theirs.
export const myShopIds = (inboxIds: number[], alertShopIds: number[] | null | undefined): number[] => {
    const mine = Array.isArray(alertShopIds) ? inboxIds.filter((id) => alertShopIds.includes(id)) : []

    return mine.length ? mine : [...inboxIds]
}
