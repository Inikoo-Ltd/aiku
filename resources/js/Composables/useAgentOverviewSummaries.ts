/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { ctrans } from "@/Composables/useTrans"
import type { AgentCard } from "@/Components/Procurement/AgentWorkCard.vue"
import type { CleanHandoverData } from "@/Components/Procurement/CleanHandoverPanel.vue"

const joined = (parts: (string | false)[]) => parts.filter(Boolean).join(" · ")

export const ordersSummary = (cards: AgentCard[] = []) => {
    const items = cards.flatMap((card) => card.current)
    const count = (type: string) => items.filter((item) => item.type === type).length
    const late = cards.reduce((total, card) => total + card.pipeline.late, 0)

    return joined([
        ctrans(":count open orders", { count: count("purchase_order") }),
        late > 0 && ctrans(":count past their ETA", { count: late }),
        count("next_container") > 0 && ctrans(":count containers at the agent", { count: count("next_container") }),
        count("stock_delivery") > 0 && ctrans(":count containers on the way", { count: count("stock_delivery") }),
    ])
}

export const agreementSummary = (agreement: { terms?: unknown[], supplier_agreement?: { suppliers?: unknown[], not_accepted?: number } | null }) => {
    const suppliers = agreement?.supplier_agreement?.suppliers?.length ?? 0
    const accepted = suppliers - (agreement?.supplier_agreement?.not_accepted ?? 0)

    return joined([
        ctrans(":count agreed terms", { count: agreement?.terms?.length ?? 0 }),
        suppliers > 0 && ctrans(":accepted/:total suppliers accepted the agreement", { accepted, total: suppliers }),
    ])
}

export const handoverSummary = (data?: CleanHandoverData) => {
    const quarter = [...(data?.quarters ?? [])].sort((a, b) => b.quarter.localeCompare(a.quarter))[0]

    return quarter ? ctrans(":quarter: :clean of :pos orders handed over clean", { quarter: quarter.quarter, clean: quarter.number_clean, pos: quarter.number_pos }) : ""
}
