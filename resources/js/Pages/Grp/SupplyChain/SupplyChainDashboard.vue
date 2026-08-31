<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Deferred, Head, Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import ProcurementOverviewPill from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewPill.vue"
import PartnerMiniShoppingList from "@/Components/Procurement/PartnerMiniShoppingList.vue"
import Modal from "@/Components/Utils/Modal.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import SearchDemandOpportunities from "@/Components/DataDisplay/Dashboard/Widget/SearchDemandOpportunities.vue"
import { trans } from "laravel-vue-i18n"

import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faCartPlus,
    faChevronDown,
    faPencil,
    faChevronRight,
    faExclamationTriangle,
    faPeopleArrows,
    faBoxUsd,
    faPersonDolly,
    faClipboardList,
    faArrowRight,
    faRadar,
    faShoppingBasket,
} from "@fal"

library.add(
    faCartPlus,
    faChevronDown,
    faPencil,
    faChevronRight,
    faExclamationTriangle,
    faPeopleArrows,
    faBoxUsd,
    faPersonDolly,
    faClipboardList,
    faArrowRight,
    faRadar,
    faShoppingBasket
)

const props = defineProps<{
    title: string
    pageHead: object
    dashboardCards: any[]
    search_demand?: any
    shoppingLists?: any
    staleOrders?: any
}>()

const sortedStaleOrders = computed(() => {
    const so = props.staleOrders
    if (!so) return []
    let rows = [
        ...(showAspos.value ? so.aspos : []),
        ...(showPos.value ? so.purchase_orders : []),
    ]
    if (agentFilter.value.size) {
        rows = rows.filter((order: any) => order.agent_code && agentFilter.value.has(order.agent_code))
    }
    const key = staleSortKey.value
    const dir = staleSortDir.value
    const valueOf = (order: any) => {
        switch (key) {
            case "order": return order.reference ?? ""
            case "agent": return order.agent_code ?? ""
            case "supplier": return order.supplier_name ?? ""
            case "state": return order.state ?? ""
            case "ordered": return order.ordered_at ?? ""
            case "amount": return order.amount_grp ?? order.amount ?? 0
            case "deposit": return order.deposit_paid_at ?? ""
            default: return daysAgo(order.deposit_paid_at ?? order.ordered_at) ?? 0
        }
    }
    return rows.sort((a, b) => {
        const va = valueOf(a)
        const vb = valueOf(b)
        return (typeof va === "number" ? va - (vb as number) : String(va).localeCompare(String(vb))) * dir
    })
})

const showAspos = ref(true)
const showPos = ref(true)
const agentFilter = ref<Set<string>>(new Set())
const toggleAgentFilter = (code: string) => {
    const next = new Set(agentFilter.value)
    next.has(code) ? next.delete(code) : next.add(code)
    agentFilter.value = next
}
const staleAgents = computed(() => {
    const counts = new Map<string, number>()
    for (const order of props.staleOrders?.aspos ?? []) {
        if (order.agent_code) counts.set(order.agent_code, (counts.get(order.agent_code) ?? 0) + 1)
    }
    return (props.staleOrders?.agents ?? []).map((agent: any) => ({
        ...agent,
        count: counts.get(agent.code) ?? 0,
    }))
})
const staleSortKey = ref<string>("waiting")
const staleSortDir = ref<1 | -1>(-1)
const setStaleSort = (key: string) => {
    if (staleSortKey.value === key) {
        staleSortDir.value = staleSortDir.value === 1 ? -1 : 1
    } else {
        staleSortKey.value = key
        staleSortDir.value = key === "waiting" ? -1 : 1
    }
}
const shoppingListTotalItems = computed(() =>
    (props.shoppingLists?.withItems ?? []).reduce((sum: number, cart: any) => sum + cart.count, 0)
)

const sortArrow = (key: string) => staleSortKey.value === key ? (staleSortDir.value === 1 ? " \u2191" : " \u2193") : ""
const isStaleDaysModalOpen = ref(false)
const staleDaysDraft = ref<number | null>(null)
const openStaleDaysModal = (currentDays: number) => {
    staleDaysDraft.value = currentDays
    isStaleDaysModalOpen.value = true
}
const saveStaleDays = () => {
    const days = Number(staleDaysDraft.value)
    if (!days || days < 1) return
    isStaleDaysModalOpen.value = false
    router.patch(
        route("grp.models.profile.update"),
        { stale_orders_days: days },
        { preserveScroll: true }
    )
}

const daysAgo = (date: string | null) => {
    if (!date) return null
    return Math.floor((Date.now() - new Date(date).getTime()) / 86400000)
}

const shortDate = (date: string | null) => {
    if (!date) return ""
    return new Date(date).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" })
}

const currencyFormat = (currency: string | null, amount: number | null) => {
    if (amount === null) return ""
    return new Intl.NumberFormat(undefined, currency ? { style: "currency", currency } : {}).format(amount)
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="mx-4 mt-3 flex flex-wrap gap-3">
        <ProcurementOverviewPill v-for="card in dashboardCards" :key="card.label" :card="card" />
    </div>

    <div class="mx-4 mt-4 flex flex-col gap-4">
        <Deferred data="shoppingLists">
            <template #fallback>
                <div class="h-16 animate-pulse rounded-lg border border-gray-200 bg-gray-100" />
            </template>

            <DashboardWidgetBox v-if="shoppingLists?.withItems?.length || shoppingLists?.empty?.length" storageKey="sc_shopping_lists_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-shopping-basket" class="text-emerald-600" fixed-width aria-hidden="true" />
                        {{ trans("Shopping lists") }}
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ shoppingLists.withItems.length }} {{ trans("with items") }}
                        · {{ shoppingListTotalItems }} {{ trans("items") }}
                        · {{ shoppingLists.empty.length }} {{ trans("empty") }}
                    </span>
                </template>

                <div v-if="shoppingLists.empty.length" class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-400">{{ trans("Empty lists:") }}</span>
                    <Link
                        v-for="list in shoppingLists.empty"
                        :key="list.name"
                        :href="route(list.route.name, list.route.parameters)"
                        class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                        <FontAwesomeIcon icon="fal fa-shopping-basket" fixed-width aria-hidden="true" />
                        {{ list.name }}
                    </Link>
                </div>

                <div v-if="shoppingLists.withItems.length" class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <PartnerMiniShoppingList
                        v-for="miniCart in shoppingLists.withItems"
                        :key="miniCart.partner_name"
                        :miniCart="miniCart" />
                </div>
            </DashboardWidgetBox>
        </Deferred>

        <Deferred data="staleOrders">
            <template #fallback>
                <div class="h-16 animate-pulse rounded-lg border border-gray-200 bg-gray-100" />
            </template>

            <DashboardWidgetBox v-if="staleOrders?.aspos?.length || staleOrders?.purchase_orders?.length" storageKey="sc_stale_orders_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
                        {{ trans("Stalled orders") }}
                        <span class="rounded-full bg-red-100 px-2 py-px text-xs font-semibold text-red-700">
                            {{ staleOrders.aspos.length + staleOrders.purchase_orders.length }}
                        </span>
                    </span>
                    <span class="flex items-center gap-1.5 text-sm font-normal text-gray-400">
                        {{ trans("open more than :days days without goods received", { days: staleOrders.threshold_days }) }}
                        <button
                            type="button"
                            v-tooltip="trans('Change threshold')"
                            class="text-gray-400 hover:text-indigo-600"
                            @click="openStaleDaysModal(staleOrders.threshold_days)">
                            <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                        </button>
                    </span>
                    <span class="flex items-center gap-1.5 text-sm font-normal">
                        <button
                            type="button"
                            class="rounded-full border px-2.5 py-px text-xs"
                            :class="showAspos ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-400 hover:bg-gray-50'"
                            @click="showAspos = !showAspos">
                            {{ staleOrders.aspos.length }} {{ trans("agent POs") }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full border px-2.5 py-px text-xs"
                            :class="showPos ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-400 hover:bg-gray-50'"
                            @click="showPos = !showPos">
                            {{ staleOrders.purchase_orders.length }} {{ trans("POs") }}
                        </button>
                    </span>
                    <span class="ml-auto flex flex-wrap items-center justify-end gap-1.5">
                        <button
                            v-for="agent in staleAgents"
                            :key="agent.code"
                            type="button"
                            v-tooltip="agent.name"
                            class="rounded-full border px-2.5 py-px text-xs uppercase"
                            :class="agentFilter.has(agent.code) ? 'border-amber-500 bg-amber-50 text-amber-700' : agent.count ? 'border-gray-200 text-gray-500 hover:bg-gray-50' : 'border-gray-100 text-gray-300 hover:bg-gray-50'"
                            @click="toggleAgentFilter(agent.code)">
                            {{ agent.count }} {{ agent.code }}
                        </button>
                    </span>
                </template>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs tabular-nums">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-[11px] uppercase tracking-wide text-gray-400">
                                <th class="cursor-pointer px-3 py-2 font-medium hover:text-gray-600" @click="setStaleSort('order')">{{ trans("Order") }}{{ sortArrow("order") }}</th>
                                <th class="cursor-pointer px-3 py-2 font-medium hover:text-gray-600" @click="setStaleSort('agent')">{{ trans("Agent") }}{{ sortArrow("agent") }}</th>
                                <th class="cursor-pointer px-3 py-2 font-medium hover:text-gray-600" @click="setStaleSort('supplier')">{{ trans("Supplier") }}{{ sortArrow("supplier") }}</th>
                                <th class="cursor-pointer px-3 py-2 font-medium hover:text-gray-600" @click="setStaleSort('state')">{{ trans("State") }}{{ sortArrow("state") }}</th>
                                <th class="cursor-pointer px-3 py-2 text-right font-medium hover:text-gray-600" @click="setStaleSort('ordered')">{{ trans("Ordered") }}{{ sortArrow("ordered") }}</th>
                                <th class="cursor-pointer px-3 py-2 text-right font-medium hover:text-gray-600" @click="setStaleSort('amount')">{{ trans("Amount") }}{{ sortArrow("amount") }}</th>
                                <th class="cursor-pointer px-3 py-2 text-right font-medium hover:text-gray-600" @click="setStaleSort('deposit')">{{ trans("Deposit paid") }}{{ sortArrow("deposit") }}</th>
                                <th class="cursor-pointer px-3 py-2 text-right font-medium hover:text-gray-600" @click="setStaleSort('waiting')">{{ trans("Waiting") }}{{ sortArrow("waiting") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="order in sortedStaleOrders"
                                :key="order.type + order.reference"
                                class="border-b border-gray-100 last:border-0 hover:bg-gray-50"
                                :class="order.deposit_paid_at && !order.has_deliveries ? 'bg-red-50 hover:bg-red-100' : ''">
                                <td class="px-3 py-1.5">
                                    <Link :href="route(order.route.name, order.route.parameters)" class="primaryLink">
                                        {{ order.reference }}
                                    </Link>
                                    <span class="ml-1 rounded bg-gray-100 px-1 py-px text-[10px] uppercase text-gray-500">
                                        {{ order.type === "aspo" ? trans("Agent PO") : (order.organisation ?? "PO") }}
                                    </span>
                                </td>
                                <td class="px-3 py-1.5 text-gray-500">
                                    <span v-if="order.agent_code" v-tooltip="order.agent_name">{{ order.agent_code }}</span>
                                </td>
                                <td class="max-w-56 px-3 py-1.5"><div v-tooltip="order.supplier_name" class="truncate">{{ order.supplier_name }}</div></td>
                                <td class="px-3 py-1.5 capitalize text-gray-500">{{ order.state }}</td>
                                <td class="px-3 py-1.5 text-right text-gray-500">{{ shortDate(order.ordered_at) }}</td>
                                <td class="px-3 py-1.5 text-right">
                                    <span v-tooltip="order.amount_grp !== null ? currencyFormat(order.currency, order.amount) : null">
                                        {{ order.amount_grp !== null ? currencyFormat(staleOrders.grp_currency, order.amount_grp) : currencyFormat(order.currency, order.amount) }}
                                    </span>
                                </td>
                                <td class="px-3 py-1.5 text-right" :class="order.deposit_paid_at ? 'font-semibold text-red-600' : 'text-gray-300'">
                                    <template v-if="order.deposit_paid_at">
                                        <span v-tooltip="order.deposit_amount_grp !== null ? currencyFormat(order.currency, order.deposit_amount) : null">
                                            {{ order.deposit_amount_grp !== null ? currencyFormat(staleOrders.grp_currency, order.deposit_amount_grp) : currencyFormat(order.currency, order.deposit_amount) }}
                                        </span>
                                        <span class="font-normal text-red-400">· {{ shortDate(order.deposit_paid_at) }}</span>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-3 py-1.5 text-right font-semibold" :class="daysAgo(order.deposit_paid_at ?? order.ordered_at) > 180 ? 'text-red-600' : 'text-amber-600'">
                                    {{ (daysAgo(order.deposit_paid_at ?? order.ordered_at) ?? 0).toLocaleString() }} {{ trans("days") }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DashboardWidgetBox>
        </Deferred>

        <Deferred data="search_demand">
            <template #fallback>
                <div class="h-16 max-w-3xl animate-pulse rounded-lg border border-gray-200 bg-gray-100" />
            </template>

            <DashboardWidgetBox v-if="search_demand" class="max-w-3xl" storageKey="sc_search_demand_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-cart-plus" class="text-green-600" fixed-width aria-hidden="true" />
                        {{ trans("Customers asked for, we do not sell") }}
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ search_demand.opportunities?.length ?? 0 }} {{ trans("terms") }}
                        · {{ trans("last :days days", { days: String(search_demand.days) }) }}
                    </span>
                </template>
                <SearchDemandOpportunities :demand="search_demand" bare />
            </DashboardWidgetBox>
        </Deferred>
    </div>

    <Modal :isOpen="isStaleDaysModalOpen" width="w-full max-w-sm" @onClose="isStaleDaysModalOpen = false">
        <div class="p-2">
            <h3 class="text-base font-semibold text-gray-700">{{ trans("Stalled orders threshold") }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ trans("Show orders open more than this many days without goods received.") }}</p>
            <div class="mt-4 flex items-center gap-2">
                <input
                    v-model.number="staleDaysDraft"
                    type="number"
                    min="1"
                    class="w-28 rounded-md border-gray-300 text-center text-lg font-semibold"
                    @keyup.enter="saveStaleDays" />
                <span class="text-sm text-gray-500">{{ trans("days") }}</span>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                    @click="isStaleDaysModalOpen = false">
                    {{ trans("Cancel") }}
                </button>
                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
                    @click="saveStaleDays">
                    {{ trans("Save") }}
                </button>
            </div>
        </div>
    </Modal>
</template>
