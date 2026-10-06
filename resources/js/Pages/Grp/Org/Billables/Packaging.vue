<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue"
import { useTabChange } from "@/Composables/tab-change"
import Image from "@common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxOpen, faFileAlt, faShoppingCart, faUsers, faTruck, faStar, faCoins, faCalendarAlt } from "@fal"

library.add(faBoxOpen, faFileAlt, faShoppingCart, faUsers, faTruck, faStar, faCoins, faCalendarAlt)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    packaging: {
        code: string
        name: string
        family_code: string
        type: string
        type_label: string
        price: number
        width: number | null
        height: number | null
        depth: number | null
        state: string
        image: any
        created_at: string
        updated_at: string
    }
    leaflets: { name: string; state: string; route: { name: string; parameters: Record<string, string> } }[]
    currencyCode: string
    tabs: { current: string; navigation: Record<string, any> }
    history?: object
    orders?: object
    stats?: {
        orders: number
        customers: number
        default_for_customers: number
        last_used_at: string | null
        revenue: number
    }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const locale = inject("locale", aikuLocaleStructure)
const money =(value: number) => locale.currencyFormat(props.currencyCode, value || 0)

const dimensions = computed(() => {
    const { width, height, depth } = props.packaging
    return width && height ? `${width} × ${height}${depth ? ` × ${depth}` : ""} mm` : "—"
})

const details = computed(() => [
    { label: ctrans("Name"), value: props.packaging.name },
    { label: ctrans("Family"), value: props.packaging.family_code },
    { label: ctrans("Type"), value: props.packaging.type_label },
    { label: ctrans("Dimensions"), value: dimensions.value },
    { label: ctrans("Price"), value: money(props.packaging.price) },
    { label: ctrans("Created"), value: useFormatTime(props.packaging.created_at) },
])

const statCards = computed(() => props.stats ? [
    { label: ctrans("Orders"), value: locale.number(props.stats.orders), icon: "fal fa-shopping-cart" },
    { label: ctrans("Customers"), value: locale.number(props.stats.customers), icon: "fal fa-users" },
    { label: ctrans("Customers' default"), value: locale.number(props.stats.default_for_customers), icon: "fal fa-star" },
    { label: ctrans("Revenue at current price"), value: money(props.stats.revenue), icon: "fal fa-coins" },
    { label: ctrans("Last used"), value: props.stats.last_used_at ? useFormatTime(props.stats.last_used_at) : "—", icon: "fal fa-calendar-alt" },
] : [])
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <TableHistories v-if="currentTab === 'history'" :data="history" tab="history" />
    <TableOrders v-else-if="currentTab === 'orders'" :data="orders" tab="orders" :useTopPagination="true" />

    <div v-else class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <div class="flex flex-col gap-4">
            <div class="rounded-md border border-gray-200 bg-white">
                <div class="flex items-center gap-4 border-b border-gray-100 px-5 py-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md bg-gray-50 text-gray-300">
                        <Image v-if="packaging.image" :src="packaging.image" :alt="packaging.name" image-cover />
                        <FontAwesomeIcon v-else icon="fal fa-box-open" class="text-2xl" fixed-width aria-hidden="true" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-lg font-semibold text-gray-900">{{ packaging.name }}</div>
                        <div class="font-mono text-xs text-gray-500">{{ packaging.code }}</div>
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-0.5 text-xs font-semibold capitalize"
                        :class="packaging.state === 'active' ? 'border-green-300 bg-green-50 text-green-700' : 'border-gray-300 bg-gray-50 text-gray-600'">
                        <span class="h-1.5 w-1.5 rounded-full" :class="packaging.state === 'active' ? 'bg-green-500' : 'bg-gray-400'" />
                        {{ packaging.state.replace('_', ' ') }}
                    </span>
                </div>
                <dl class="divide-y divide-gray-100 px-5 text-sm">
                    <div v-for="row in details" :key="row.label" class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-gray-500">{{ row.label }}</dt>
                        <dd class="truncate text-right text-gray-900">{{ row.value }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-md border border-gray-200 bg-white">
                <div class="flex items-center gap-2 border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-800">
                    <FontAwesomeIcon icon="fal fa-file-alt" fixed-width class="text-[--app-accent-strong]" aria-hidden="true" />
                    {{ ctrans("Default leaflets") }}
                    <span class="rounded-full bg-gray-100 px-1.5 text-xs font-normal tabular-nums text-gray-500">{{ leaflets.length }}</span>
                </div>
                <ul v-if="leaflets.length" class="divide-y divide-gray-100 text-sm">
                    <li v-for="leaflet in leaflets" :key="leaflet.name" class="flex items-center justify-between gap-2 px-5 py-2">
                        <Link :href="route(leaflet.route.name, leaflet.route.parameters)" class="primaryLink truncate">{{ leaflet.name }}</Link>
                        <span class="shrink-0 text-xs capitalize text-gray-400">{{ leaflet.state.replace('_', ' ') }}</span>
                    </li>
                </ul>
                <p v-else class="px-5 py-4 text-sm text-gray-400">{{ ctrans("No leaflets go with this packaging family") }}</p>
            </div>
        </div>

        <div class="rounded-md border border-gray-200 bg-white p-4">
            <div class="mb-3 text-sm font-semibold text-gray-800">{{ ctrans("Usage") }}</div>
            <div v-if="stats" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="card in statCards" :key="card.label" class="flex items-start gap-3 rounded-md border border-gray-100 bg-gray-50/60 p-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-[--app-accent-soft] text-[--app-accent-strong]">
                        <FontAwesomeIcon :icon="card.icon" fixed-width aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium uppercase tracking-wide text-gray-500">{{ card.label }}</div>
                        <div class="mt-0.5 truncate text-lg font-semibold tabular-nums text-gray-900">{{ card.value }}</div>
                    </div>
                </div>
            </div>
            <div v-else class="grid animate-pulse grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="skeleton in 5" :key="skeleton" class="flex items-start gap-3 rounded-md border border-gray-100 p-3">
                    <div class="h-9 w-9 rounded-md bg-gray-200" />
                    <div class="flex-1 space-y-2">
                        <div class="h-3 w-2/3 rounded bg-gray-200" />
                        <div class="h-5 w-1/2 rounded bg-gray-200" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
