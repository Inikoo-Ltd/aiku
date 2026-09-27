<script setup lang="ts">
import { ref, inject, watch, onMounted, computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFolderTree, faArrowRight, faBoxOpen, faBullhorn, faCodeBranch, faCube, faEnvelope, faFolderOpen, faGlobe, faUserFriends, faUserPlus } from "@fal"

library.add(faFolderTree, faArrowRight, faBoxOpen, faBullhorn, faCodeBranch, faCube, faEnvelope, faFolderOpen, faGlobe, faUserFriends, faUserPlus)

const props = defineProps<{
    fetchRoute: { name: string, parameters: Record<string, string> }
    interval: string
    only?: string[]
}>()

const shows = (key: string) => !props.only || props.only.includes(key)

const locale = inject("locale", aikuLocaleStructure)

const data = ref<any>(null)
const isLoading = ref(true)

const fetchWidgets = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(route(props.fetchRoute.name, props.fetchRoute.parameters), {
            params: { interval: props.interval, only: props.only?.join(",") || undefined },
        })
        data.value = response.data
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchWidgets)
watch(() => props.interval, fetchWidgets)

const money = (amount: number) =>
    new Intl.NumberFormat(undefined, { style: "currency", currency: data.value?.currency_code ?? "GBP", maximumFractionDigits: 0 }).format(Math.round(amount))
const signedMoney = (amount: number) => (amount > 0 ? "+" : "−") + money(Math.abs(amount))
const moversPeriod = (movers: any) => movers?.period === "last_month"
    ? ctrans("Last month against the same month last year")
    : ctrans("This month so far against the same days last year")
const link = (key: string, param: string, value: string) => {
    const target = data.value?.routes?.[key]
    return target ? route(target.name, { ...target.parameters, [param]: value }) : null
}
const share = (value: number, rows: any[], field: string) => {
    const max = Math.max(...rows.map((row) => Number(row[field]) || 0), 0)
    return max > 0 ? Math.round((value / max) * 100) : 0
}
const percent = (part: number, total: number) => (total > 0 ? ((part / total) * 100).toFixed(1) + "%" : "–")

const deliveryStateLabel = (state: string) => ({
    in_process: ctrans("On order"),
    confirmed: ctrans("Confirmed"),
    ready_to_ship: ctrans("Ready to ship"),
    dispatched: ctrans("Dispatched"),
}[state] ?? state)

const cards = computed(() => allCards.value.filter((card) => shows(card.key)))

const allCards = computed(() => [
    { key: "department_movers", title: ctrans("Departments: biggest changes"), icon: "fal fa-folder-tree", rows: [...(data.value?.department_movers?.growing ?? []), ...(data.value?.department_movers?.falling ?? [])], movers: data.value?.department_movers, linkKey: "department" },
    { key: "family_movers", title: ctrans("Families: biggest changes"), icon: "fal fa-folder-open", rows: [...(data.value?.family_movers?.growing ?? []), ...(data.value?.family_movers?.falling ?? [])], movers: data.value?.family_movers, linkKey: "family" },
    { key: "channels", title: ctrans("Sales by channel"), icon: "fal fa-code-branch", rows: data.value?.channels ?? [] },
    { key: "top_customers", title: ctrans("Top customers"), icon: "fal fa-user-friends", rows: data.value?.top_customers ?? [], viewAll: "customers" },
    { key: "top_products", title: ctrans("Top products"), icon: "fal fa-cube", rows: data.value?.top_products ?? [], viewAll: "products" },
    { key: "top_families", title: ctrans("Top families"), icon: "fal fa-folder-open", rows: data.value?.top_families ?? [], viewAll: "families" },
    { key: "out_of_stock", title: ctrans("Out of stock: sales we are losing"), icon: "fal fa-box-open", rows: data.value?.out_of_stock?.rows ?? [] },
    { key: "top_webpages", title: ctrans("Most visited pages"), icon: "fal fa-globe", rows: data.value?.top_webpages ?? [] },
    { key: "marketing", title: ctrans("Best performing marketing"), icon: "fal fa-bullhorn", rows: data.value?.marketing?.channels ?? [], viewAll: "marketing" },
    { key: "email", title: ctrans("Email marketing"), icon: "fal fa-envelope", rows: data.value?.email?.mailshots ?? [], viewAll: "mailshots" },
])
</script>

<template>
    <div class="px-4 pb-6">
        <div v-if="isLoading && !data" class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4">
            <div v-for="i in (only?.length ?? 9)" :key="i" class="h-64 rounded-lg border bg-gray-50 animate-pulse" />
        </div>

        <div v-else-if="data" class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4" :class="isLoading ? 'opacity-50' : ''">
            <div v-if="shows('subscriptions')" class="rounded-lg border bg-gray-50 shadow-sm p-4 flex flex-col">
                <div class="flex items-center gap-2 text-lg font-bold mb-3">
                    <FontAwesomeIcon icon="fal fa-user-plus" fixed-width class="text-gray-400" aria-hidden="true" />
                    {{ ctrans("Registrations vs unsubscribes") }}
                </div>
                <div class="grid grid-cols-3 gap-2 text-center flex-1 items-center">
                    <div>
                        <div class="text-2xl font-bold text-green-600">{{ locale.number(data.subscriptions.registrations) }}</div>
                        <div class="text-xs text-gray-500">{{ ctrans("Registrations") }}</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-red-500">{{ locale.number(data.subscriptions.unsubscribed) }}</div>
                        <div class="text-xs text-gray-500">{{ ctrans("Unsubscribed") }}</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" :class="data.subscriptions.net >= 0 ? 'text-green-600' : 'text-red-500'">
                            {{ data.subscriptions.net >= 0 ? "+" : "" }}{{ locale.number(data.subscriptions.net) }}
                        </div>
                        <div class="text-xs text-gray-500">{{ data.subscriptions.net >= 0 ? ctrans("Winning") : ctrans("Losing") }}</div>
                    </div>
                </div>
                <div v-if="data.marketing?.totals" class="mt-3 pt-3 border-t grid grid-cols-3 gap-2 text-center text-xs text-gray-500">
                    <div><span class="font-semibold text-gray-700">{{ money(data.marketing.totals.spend) }}</span><br />{{ ctrans("Ad spend") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ money(data.marketing.totals.revenue) }}</span><br />{{ ctrans("Attributed revenue") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ data.marketing.totals.roas != null ? Number(data.marketing.totals.roas).toFixed(1) + "×" : "–" }}</span><br />{{ ctrans("ROAS") }}</div>
                </div>
            </div>

            <div v-for="card in cards" :key="card.key" class="rounded-lg border bg-gray-50 shadow-sm p-4 flex flex-col">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2 text-lg font-bold">
                        <FontAwesomeIcon :icon="card.icon" fixed-width class="text-gray-400" aria-hidden="true" />
                        {{ card.title }}
                    </div>
                    <Link v-if="card.viewAll && data.routes?.[card.viewAll]" :href="route(data.routes[card.viewAll].name, data.routes[card.viewAll].parameters)" class="text-xs text-gray-500 hover:text-gray-800 inline-flex items-center gap-1">
                        {{ ctrans("View all") }}
                        <FontAwesomeIcon icon="fal fa-arrow-right" fixed-width aria-hidden="true" />
                    </Link>
                </div>

                <div v-if="card.key === 'email' && data.email?.totals" class="grid grid-cols-4 gap-2 text-center text-xs text-gray-500 mb-3 pb-3 border-b">
                    <div><span class="font-semibold text-gray-700">{{ locale.number(data.email.totals.sent) }}</span><br />{{ ctrans("Sent") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ percent(data.email.totals.opened, data.email.totals.sent) }}</span><br />{{ ctrans("Opened") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ percent(data.email.totals.clicked, data.email.totals.sent) }}</span><br />{{ ctrans("Clicked") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ money(data.email.totals.attributed_revenue) }}</span><br />{{ ctrans("Revenue") }}</div>
                </div>

                <p v-if="card.key === 'out_of_stock' && data.out_of_stock?.products" class="-mt-2 mb-3 text-xs text-gray-500">
                    {{ ctrans(":count products out of stock, about :amount of sales lost while out. Estimated from what each sold in the 90 days before it ran out.", { count: locale.number(data.out_of_stock.products), amount: money(data.out_of_stock.estimated_lost) }) }}
                </p>

                <div v-if="!card.rows.length" class="text-sm text-gray-400 italic flex-1 flex items-center justify-center">
                    {{ ctrans("Nothing in this period") }}
                </div>

                <div v-else-if="card.movers" class="text-sm">
                    <p class="-mt-2 mb-3 text-xs text-gray-500">{{ moversPeriod(card.movers) }}</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="side in ['growing', 'falling']" :key="side">
                            <p class="mb-1 text-xs font-semibold" :class="side === 'growing' ? 'text-green-700' : 'text-red-700'">
                                {{ side === "growing" ? ctrans("Growing") : ctrans("Falling") }}
                            </p>
                            <p v-if="!card.movers[side].length" class="text-xs italic text-gray-400">{{ ctrans("None") }}</p>
                            <ul v-else class="space-y-1">
                                <li v-for="row in card.movers[side]" :key="row.slug" class="flex items-center justify-between gap-2">
                                    <Link :href="link(card.linkKey, card.linkKey, row.slug)" class="min-w-0 truncate hover:underline" :title="row.name">{{ row.name }}</Link>
                                    <span class="shrink-0 font-semibold tabular-nums" :class="side === 'growing' ? 'text-green-600' : 'text-red-600'" :title="ctrans(':now now, :before last year', { now: money(row.sales), before: money(row.sales_last_year) })">
                                        {{ signedMoney(row.change) }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <ul v-else class="space-y-1.5 text-sm">
                    <li v-for="(row, index) in card.rows" :key="index" class="relative">
                        <div class="absolute inset-y-0 left-0 rounded bg-indigo-100/70" :style="{ width: share(Number(row.sales ?? row.estimated_lost ?? row.revenue ?? row.page_views ?? row.attributed_revenue ?? 0), card.rows, row.sales != null ? 'sales' : row.estimated_lost != null ? 'estimated_lost' : row.revenue != null ? 'revenue' : row.page_views != null ? 'page_views' : 'attributed_revenue') + '%' }" />
                        <div class="relative flex items-center gap-2 px-1.5 py-0.5">
                            <span class="w-4 text-xs text-gray-400 text-right shrink-0">{{ index + 1 }}</span>

                            <template v-if="card.key === 'channels'">
                                <span class="flex-1 truncate">{{ row.name }}</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ row.share }}% · {{ locale.number(row.invoices) }} {{ ctrans("inv") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.sales) }}</span>
                            </template>

                            <template v-else-if="card.key === 'top_customers'">
                                <Link :href="link('customer', 'customer', row.slug)" class="flex-1 truncate hover:underline">{{ row.name }}</Link>
                                <span class="text-xs text-gray-500 shrink-0">{{ locale.number(row.invoices) }} {{ ctrans("inv") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.sales) }}</span>
                            </template>

                            <template v-else-if="card.key === 'top_products'">
                                <Link :href="link('product', 'product', row.slug)" class="flex-1 truncate hover:underline" :title="row.name"><span class="font-mono text-xs text-gray-500 mr-1">{{ row.code }}</span>{{ row.name }}</Link>
                                <span class="text-xs text-gray-500 shrink-0">{{ locale.number(row.sold) }} {{ ctrans("sold") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.sales) }}</span>
                            </template>

                            <template v-else-if="card.key === 'top_families'">
                                <Link :href="link('family', 'family', row.slug)" class="flex-1 truncate hover:underline" :title="row.name"><span class="font-mono text-xs text-gray-500 mr-1">{{ row.code }}</span>{{ row.name }}</Link>
                                <span class="text-xs text-gray-500 shrink-0">{{ locale.number(row.invoices) }} {{ ctrans("inv") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.sales) }}</span>
                            </template>

                            <template v-else-if="card.key === 'out_of_stock'">
                                <Link :href="link('product', 'product', row.slug)" class="flex-1 truncate hover:underline" :title="row.name"><span class="font-mono text-xs text-gray-500 mr-1">{{ row.code }}</span>{{ row.name }}</Link>
                                <span v-if="row.on_order" class="text-xs shrink-0 rounded px-1.5 py-0.5 bg-amber-100 text-amber-800" :title="row.on_order.reference">
                                    {{ deliveryStateLabel(row.on_order.delivery_state) }} · {{ row.on_order.date }}
                                </span>
                                <span v-else class="text-xs shrink-0 rounded px-1.5 py-0.5 bg-red-100 text-red-700">{{ ctrans("Not on order") }}</span>
                                <span class="text-xs text-gray-500 shrink-0" :title="row.out_of_stock_since">{{ ctrans(":days d", { days: String(row.days_out) }) }}</span>
                                <span class="font-semibold tabular-nums shrink-0 text-red-600">−{{ money(row.estimated_lost) }}</span>
                            </template>

                            <template v-else-if="card.key === 'top_webpages'">
                                <Link v-if="link('webpage', 'webpage', row.slug)" :href="link('webpage', 'webpage', row.slug)" class="flex-1 truncate hover:underline" :title="row.url">{{ row.title }}</Link>
                                <span v-else class="flex-1 truncate" :title="row.url">{{ row.title }}</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ locale.number(row.visitors) }} {{ ctrans("visitors") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ locale.number(row.page_views) }}</span>
                            </template>

                            <template v-else-if="card.key === 'marketing'">
                                <Link v-if="row.route" :href="route(row.route.name, row.route.parameters)" class="flex-1 truncate hover:underline">{{ row.name }}</Link>
                                <span v-else class="flex-1 truncate">{{ row.name }}</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ money(row.spend) }} {{ ctrans("spend") }} · {{ row.roas != null ? Number(row.roas).toFixed(1) + "×" : "–" }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.revenue) }}</span>
                            </template>

                            <template v-else-if="card.key === 'email'">
                                <span class="flex-1 truncate" :title="row.subject">{{ row.subject }}</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ percent(row.opened, row.sent) }} · {{ percent(row.clicked, row.sent) }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.attributed_revenue) }}</span>
                            </template>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
