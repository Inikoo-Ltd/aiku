<script setup lang="ts">
import { ref, inject, watch, onMounted, computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowRight, faBoxOpen, faBullhorn, faCodeBranch, faCube, faEnvelope, faFolderOpen, faGlobe, faUserFriends, faUserPlus } from "@fal"

library.add(faArrowRight, faBoxOpen, faBullhorn, faCodeBranch, faCube, faEnvelope, faFolderOpen, faGlobe, faUserFriends, faUserPlus)

const props = defineProps<{
    fetchRoute: { name: string, parameters: Record<string, string> }
    interval: string
}>()

const locale = inject("locale", aikuLocaleStructure)

const data = ref<any>(null)
const isLoading = ref(true)

const fetchWidgets = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(route(props.fetchRoute.name, props.fetchRoute.parameters), {
            params: { interval: props.interval },
        })
        data.value = response.data
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchWidgets)
watch(() => props.interval, fetchWidgets)

const money = (amount: number) => locale.currencyFormat(data.value?.currency_code, amount)
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
    in_process: trans("On order"),
    confirmed: trans("Confirmed"),
    ready_to_ship: trans("Ready to ship"),
    dispatched: trans("Dispatched"),
}[state] ?? state)

const cards = computed(() => [
    { key: "channels", title: trans("Sales by channel"), icon: "fal fa-code-branch", rows: data.value?.channels ?? [] },
    { key: "top_customers", title: trans("Top customers"), icon: "fal fa-user-friends", rows: data.value?.top_customers ?? [], viewAll: "customers" },
    { key: "top_products", title: trans("Top products"), icon: "fal fa-cube", rows: data.value?.top_products ?? [], viewAll: "products" },
    { key: "top_families", title: trans("Top families"), icon: "fal fa-folder-open", rows: data.value?.top_families ?? [], viewAll: "families" },
    { key: "out_of_stock", title: trans("Best sellers out of stock"), icon: "fal fa-box-open", rows: data.value?.out_of_stock ?? [] },
    { key: "top_webpages", title: trans("Most visited pages"), icon: "fal fa-globe", rows: data.value?.top_webpages ?? [] },
    { key: "marketing", title: trans("Best performing marketing"), icon: "fal fa-bullhorn", rows: data.value?.marketing?.channels ?? [], viewAll: "marketing" },
    { key: "email", title: trans("Email marketing"), icon: "fal fa-envelope", rows: data.value?.email?.mailshots ?? [], viewAll: "mailshots" },
])
</script>

<template>
    <div class="px-4 pb-6">
        <div v-if="isLoading && !data" class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4">
            <div v-for="i in 9" :key="i" class="h-64 rounded-lg border bg-gray-50 animate-pulse" />
        </div>

        <div v-else-if="data" class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4" :class="isLoading ? 'opacity-50' : ''">
            <div class="rounded-lg border bg-gray-50 shadow-sm p-4 flex flex-col">
                <div class="flex items-center gap-2 text-lg font-bold mb-3">
                    <FontAwesomeIcon icon="fal fa-user-plus" fixed-width class="text-gray-400" aria-hidden="true" />
                    {{ trans("Registrations vs unsubscribes") }}
                </div>
                <div class="grid grid-cols-3 gap-2 text-center flex-1 items-center">
                    <div>
                        <div class="text-2xl font-bold text-green-600">{{ locale.number(data.subscriptions.registrations) }}</div>
                        <div class="text-xs text-gray-500">{{ trans("Registrations") }}</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-red-500">{{ locale.number(data.subscriptions.unsubscribed) }}</div>
                        <div class="text-xs text-gray-500">{{ trans("Unsubscribed") }}</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" :class="data.subscriptions.net >= 0 ? 'text-green-600' : 'text-red-500'">
                            {{ data.subscriptions.net >= 0 ? "+" : "" }}{{ locale.number(data.subscriptions.net) }}
                        </div>
                        <div class="text-xs text-gray-500">{{ data.subscriptions.net >= 0 ? trans("Winning") : trans("Losing") }}</div>
                    </div>
                </div>
                <div v-if="data.marketing?.totals" class="mt-3 pt-3 border-t grid grid-cols-3 gap-2 text-center text-xs text-gray-500">
                    <div><span class="font-semibold text-gray-700">{{ money(data.marketing.totals.spend) }}</span><br />{{ trans("Ad spend") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ money(data.marketing.totals.revenue) }}</span><br />{{ trans("Attributed revenue") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ data.marketing.totals.roas != null ? Number(data.marketing.totals.roas).toFixed(1) + "×" : "–" }}</span><br />{{ trans("ROAS") }}</div>
                </div>
            </div>

            <div v-for="card in cards" :key="card.key" class="rounded-lg border bg-gray-50 shadow-sm p-4 flex flex-col">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2 text-lg font-bold">
                        <FontAwesomeIcon :icon="card.icon" fixed-width class="text-gray-400" aria-hidden="true" />
                        {{ card.title }}
                    </div>
                    <Link v-if="card.viewAll && data.routes?.[card.viewAll]" :href="route(data.routes[card.viewAll].name, data.routes[card.viewAll].parameters)" class="text-xs text-gray-500 hover:text-gray-800 inline-flex items-center gap-1">
                        {{ trans("View all") }}
                        <FontAwesomeIcon icon="fal fa-arrow-right" aria-hidden="true" />
                    </Link>
                </div>

                <div v-if="card.key === 'email' && data.email?.totals" class="grid grid-cols-4 gap-2 text-center text-xs text-gray-500 mb-3 pb-3 border-b">
                    <div><span class="font-semibold text-gray-700">{{ locale.number(data.email.totals.sent) }}</span><br />{{ trans("Sent") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ percent(data.email.totals.opened, data.email.totals.sent) }}</span><br />{{ trans("Opened") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ percent(data.email.totals.clicked, data.email.totals.sent) }}</span><br />{{ trans("Clicked") }}</div>
                    <div><span class="font-semibold text-gray-700">{{ money(data.email.totals.attributed_revenue) }}</span><br />{{ trans("Revenue") }}</div>
                </div>

                <div v-if="!card.rows.length" class="text-sm text-gray-400 italic flex-1 flex items-center justify-center">
                    {{ trans("Nothing in this period") }}
                </div>

                <ul v-else class="space-y-1.5 text-sm">
                    <li v-for="(row, index) in card.rows" :key="index" class="relative">
                        <div class="absolute inset-y-0 left-0 rounded bg-indigo-100/70" :style="{ width: share(Number(row.sales ?? row.revenue ?? row.page_views ?? row.attributed_revenue ?? 0), card.rows, row.sales != null ? 'sales' : row.revenue != null ? 'revenue' : row.page_views != null ? 'page_views' : 'attributed_revenue') + '%' }" />
                        <div class="relative flex items-center gap-2 px-1.5 py-0.5">
                            <span class="w-4 text-xs text-gray-400 text-right shrink-0">{{ index + 1 }}</span>

                            <template v-if="card.key === 'channels'">
                                <span class="flex-1 truncate">{{ row.name }}</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ row.share }}% · {{ locale.number(row.invoices) }} {{ trans("inv") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.sales) }}</span>
                            </template>

                            <template v-else-if="card.key === 'top_customers'">
                                <Link :href="link('customer', 'customer', row.slug)" class="flex-1 truncate hover:underline">{{ row.name }}</Link>
                                <span class="text-xs text-gray-500 shrink-0">{{ locale.number(row.invoices) }} {{ trans("inv") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.sales) }}</span>
                            </template>

                            <template v-else-if="card.key === 'top_products'">
                                <Link :href="link('product', 'product', row.slug)" class="flex-1 truncate hover:underline" :title="row.name"><span class="font-mono text-xs text-gray-500 mr-1">{{ row.code }}</span>{{ row.name }}</Link>
                                <span class="text-xs text-gray-500 shrink-0">{{ locale.number(row.sold) }} {{ trans("sold") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.sales) }}</span>
                            </template>

                            <template v-else-if="card.key === 'top_families'">
                                <Link :href="link('family', 'family', row.slug)" class="flex-1 truncate hover:underline" :title="row.name"><span class="font-mono text-xs text-gray-500 mr-1">{{ row.code }}</span>{{ row.name }}</Link>
                                <span class="text-xs text-gray-500 shrink-0">{{ locale.number(row.invoices) }} {{ trans("inv") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.sales) }}</span>
                            </template>

                            <template v-else-if="card.key === 'out_of_stock'">
                                <Link :href="link('product', 'product', row.slug)" class="flex-1 truncate hover:underline" :title="row.name"><span class="font-mono text-xs text-gray-500 mr-1">{{ row.code }}</span>{{ row.name }}</Link>
                                <span v-if="row.on_order" class="text-xs shrink-0 rounded px-1.5 py-0.5 bg-amber-100 text-amber-800" :title="row.on_order.reference">
                                    {{ deliveryStateLabel(row.on_order.delivery_state) }} · {{ row.on_order.date }}
                                </span>
                                <span v-else class="text-xs shrink-0 rounded px-1.5 py-0.5 bg-red-100 text-red-700">{{ trans("Not on order") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ money(row.sales) }}</span>
                            </template>

                            <template v-else-if="card.key === 'top_webpages'">
                                <Link v-if="link('webpage', 'webpage', row.slug)" :href="link('webpage', 'webpage', row.slug)" class="flex-1 truncate hover:underline" :title="row.url">{{ row.title }}</Link>
                                <span v-else class="flex-1 truncate" :title="row.url">{{ row.title }}</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ locale.number(row.visitors) }} {{ trans("visitors") }}</span>
                                <span class="font-semibold tabular-nums shrink-0">{{ locale.number(row.page_views) }}</span>
                            </template>

                            <template v-else-if="card.key === 'marketing'">
                                <Link v-if="row.route" :href="route(row.route.name, row.route.parameters)" class="flex-1 truncate hover:underline">{{ row.name }}</Link>
                                <span v-else class="flex-1 truncate">{{ row.name }}</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ money(row.spend) }} {{ trans("spend") }} · {{ row.roas != null ? Number(row.roas).toFixed(1) + "×" : "–" }}</span>
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
