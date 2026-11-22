<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import axios from "axios"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faClipboardList, faThList, faThLarge } from "@fal"
import AgentWorkCard, { type AgentCard, type CoverBucket } from "@/Components/Procurement/AgentWorkCard.vue"
import OverviewSection from "@/Components/Utils/OverviewSection.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ordersSummary } from "@/Composables/useAgentOverviewSummaries"

library.add(faClipboardList, faThList, faThLarge)

const props = defineProps<{
    cards: AgentCard[]
    cover?: Record<number, CoverBucket[]>
    sections: Record<string, boolean>
    cardSections: Record<string, boolean>
    heading?: "agent" | "organisation"
}>()

const locale = useLocaleStore()

const view = computed({
    get: () => (props.sections.agent_orders_table ? "table" : "cards"),
    set: (value: string) => {
        props.sections.agent_orders_table = value === "table"
        axios.patch(route("grp.models.profile.update"), { overview_sections: props.sections })
    },
})

const money = (amount: number, currency: string) =>
    new Intl.NumberFormat(locale.locale_iso || undefined, { style: "currency", currency, currencyDisplay: "narrowSymbol", maximumFractionDigits: 0 }).format(amount)

const count = (card: AgentCard, type: string) => card.current.filter((item) => item.type === type).length

const stageOrders = (card: AgentCard, stage: string) => card.pipeline.stages.find((row) => row.stage === stage)?.orders ?? 0

const ordersValue = (card: AgentCard) => card.current.filter((item) => item.type === "purchase_order").reduce((total, item) => total + (item.value ?? 0), 0)

const lost = (card: AgentCard, key: "lost" | "lost_untouched") => (props.cover?.[card.id] ?? []).reduce((total, bucket) => total + bucket[key], 0)

const agentUrl = (card: AgentCard, routeName = "grp.org.procurement.org_agents.show") => route(routeName, [card.organisation_slug, card.slug])

const number = (value: number) => (value ? locale.number(value) : "")
</script>

<template>
    <OverviewSection sectionKey="agent_orders" :title="ctrans('Orders and containers')" icon="fal fa-clipboard-list" :sections="sections" :summary="ordersSummary(cards)">
        <template #actions>
            <SegmentedToggle
                v-model="view"
                iconOnly
                soft
                :ariaLabel="ctrans('Show as cards or table')"
                :options="[
                    { label: ctrans('Cards'), value: 'cards', icon: 'fal fa-th-large' },
                    { label: ctrans('Table'), value: 'table', icon: 'fal fa-th-list' },
                ]" />
        </template>

        <div v-if="view === 'cards'" class="grid max-w-[120rem] gap-4 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
            <AgentWorkCard v-for="card in cards" :key="card.id" :agent="card" :cover="cover" :sections="cardSections" :heading="heading" hideFlag />
        </div>

        <div v-else class="max-w-[120rem] overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-sm tabular-nums text-gray-700">
                <thead class="whitespace-nowrap border-b border-gray-200 bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">{{ heading === "organisation" ? ctrans("Organisation") : ctrans("Agent") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Lost sales") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Not ordered yet") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Open orders") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Value") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Not confirmed") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Past ETA") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("No ETA") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("At the agent") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("On the way") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Money to pay") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="card in cards" :key="card.id" class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-900">
                            <Link :href="agentUrl(card)" class="hover:underline">{{ heading === "organisation" ? card.organisation_code : card.name }}</Link>
                        </td>
                        <td class="px-3 py-2 text-right font-medium text-red-700">
                            <Link v-if="cover && lost(card, 'lost')" :href="agentUrl(card, 'grp.org.procurement.org_agents.show.shopping.dashboard')" class="hover:underline">{{ money(lost(card, "lost"), card.organisation_currency_code) }}</Link>
                            <span v-else-if="!cover" class="inline-block h-3 w-12 animate-pulse rounded bg-gray-100" />
                        </td>
                        <td class="px-3 py-2 text-right">
                            <template v-if="cover && lost(card, 'lost_untouched')">{{ money(lost(card, "lost_untouched"), card.organisation_currency_code) }}</template>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Link v-if="count(card, 'purchase_order')" :href="agentUrl(card, 'grp.org.procurement.org_agents.show.order_pipeline')" class="hover:underline">{{ number(count(card, "purchase_order")) }}</Link>
                        </td>
                        <td class="px-3 py-2 text-right">{{ ordersValue(card) ? money(ordersValue(card), card.organisation_currency_code) : "" }}</td>
                        <td class="px-3 py-2 text-right">{{ number(stageOrders(card, "submitted")) }}</td>
                        <td class="px-3 py-2 text-right text-amber-700">{{ number(card.pipeline.late) }}</td>
                        <td class="px-3 py-2 text-right text-amber-700">{{ number(card.pipeline.no_eta) }}</td>
                        <td class="px-3 py-2 text-right">{{ number(count(card, "next_container")) }}</td>
                        <td class="px-3 py-2 text-right">{{ number(count(card, "stock_delivery")) }}</td>
                        <td class="px-3 py-2 text-right text-amber-700">{{ number(card.open_money_requests) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </OverviewSection>
</template>
