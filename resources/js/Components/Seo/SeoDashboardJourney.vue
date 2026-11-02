<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { Link, router, usePage } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { watchDebounced } from "@vueuse/core"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import MultiSelect from "primevue/multiselect"
import Paginator from "primevue/paginator"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch, faTimes, faFilter } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SeoDashboardJourneyDetail from "@/Components/Seo/SeoDashboardJourneyDetail.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import type { JourneyData, JourneyOrder, JourneySource, OrderJourney } from "@/types/Components/SeoJourney"

library.add(faSearch, faTimes, faFilter)

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: JourneyData | null
    tab: string
}>()

const locale = useLocaleStore()
const page = usePage()

const query = new URLSearchParams(window.location.search)
const selectedDays = ref(query.get("journey_days") ?? "30")
const isLoading = ref(false)
const selectedPurchaseId = ref<number | null>(null)
const isDetailOpen = ref(false)
const currentPage = ref(Math.max(1, Number(query.get("journey_page")) || 1))
const tableTop = ref<HTMLElement | null>(null)

const amountFromQuery = (key: string) => {
    const value = query.get(key)
    return value !== null && value !== "" && !Number.isNaN(Number(value)) ? Number(value) : null
}

const emptyFilters = () => ({
    search: "",
    sources: [] as string[],
    visits: "",
    min: null as number | null,
    max: null as number | null,
    landing: "",
})

const filters = reactive({
    search: query.get("journey_search") ?? "",
    sources: (query.get("journey_sources") ?? "").split(",").filter(Boolean),
    visits: query.get("journey_visits") ?? "",
    min: amountFromQuery("journey_min"),
    max: amountFromQuery("journey_max"),
    landing: query.get("journey_landing") ?? "",
})

const amountDrafts = reactive({ min: filters.min, max: filters.max })

watch(() => amountDrafts.min, (value) => filters.min = value ?? null)
watch(() => amountDrafts.max, (value) => filters.max = value ?? null)

const dayOptions = [
    { label: ctrans(":days days", { days: 30 }), value: "30" },
    { label: ctrans(":days days", { days: 90 }), value: "90" },
    { label: ctrans(":days days", { days: 180 }), value: "180" },
]

const visitOptions = [
    { label: ctrans("Any"), value: "" },
    { label: "1", value: "1" },
    { label: "2–3", value: "2-3" },
    { label: "4+", value: "4+" },
]

const sourceOptions = computed(() => {
    const options = props.data?.source_options ?? []
    const known = new Set(options.map((option) => option.value))

    return [...options, ...filters.sources.filter((source) => !known.has(source)).map((source) => ({ value: source, label: source }))]
})

const hasFilters = computed(() => Boolean(
    filters.search.trim() || filters.sources.length || filters.visits || filters.min !== null || filters.max !== null || filters.landing.trim()
))

const journeyUrl = (extra: Record<string, string> = {}) => {
    const params = new URLSearchParams({ tab: props.tab, journey_days: selectedDays.value })

    if (filters.search.trim()) params.set("journey_search", filters.search.trim())
    if (filters.sources.length) params.set("journey_sources", filters.sources.join(","))
    if (filters.visits) params.set("journey_visits", filters.visits)
    if (filters.min !== null) params.set("journey_min", String(filters.min))
    if (filters.max !== null) params.set("journey_max", String(filters.max))
    if (filters.landing.trim()) params.set("journey_landing", filters.landing.trim())
    if (currentPage.value > 1) params.set("journey_page", String(currentPage.value))

    Object.entries(extra).forEach(([key, value]) => params.set(key, value))

    return `${window.location.pathname}?${params.toString()}`
}

let requestedUrl = journeyUrl()

const reload = () => {
    const url = journeyUrl()
    if (url === requestedUrl) return
    requestedUrl = url

    router.get(url, {}, {
        only: [props.tab],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => isLoading.value = true,
        onFinish: () => isLoading.value = false,
    })
}

const reloadFromFirstPage = () => {
    currentPage.value = 1
    reload()
}

watch([selectedDays, () => filters.sources, () => filters.visits], reloadFromFirstPage, { deep: true })
watchDebounced([() => filters.search, () => filters.landing, () => filters.min, () => filters.max], reloadFromFirstPage, { debounce: 400 })

watch(() => props.data?.page, (page) => {
    if (page) currentPage.value = page
})

const clearFilters = () => {
    Object.assign(filters, emptyFilters())
    Object.assign(amountDrafts, { min: null, max: null })
    reloadFromFirstPage()
}

const changePage = (event: { page: number }) => {
    currentPage.value = event.page + 1
    reload()
    tableTop.value?.scrollIntoView({ behavior: "smooth", block: "start" })
}

const amountFromInput = (event: { value: number | string | null }) => typeof event.value === "number" ? event.value : null

const amountInputOptions = computed(() => props.data?.currency_code
    ? locale.currencyInputOptions(props.data.currency_code)
    : { minFractionDigits: 0, maxFractionDigits: 2 })

const countText = computed(() => {
    if (!props.data) return ""

    const { total, matching, page, per_page: perPage, orders } = props.data
    const range = {
        from: locale.number((page - 1) * perPage + 1),
        to: locale.number((page - 1) * perPage + orders.length),
        matching: locale.number(matching),
        total: locale.number(total),
    }

    if (hasFilters.value) {
        return matching > orders.length
            ? ctrans("Showing :from–:to of :matching matching orders, out of :total tracked", range)
            : ctrans("Showing :matching of :total tracked orders", range)
    }

    return total > orders.length
        ? ctrans("Showing :from–:to of :total tracked orders", range)
        : ctrans("Showing all :total tracked orders", range)
})

const detail = computed(() => {
    const journey = page.props.journey_order as OrderJourney | null | undefined

    return journey && journey.purchase_id === selectedPurchaseId.value ? journey : null
})

const openJourney = (purchaseId: number) => {
    selectedPurchaseId.value = purchaseId
    isDetailOpen.value = true

    router.get(journeyUrl({ journey_order: String(purchaseId) }), {}, {
        only: ["journey_order"],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const sourceText = (source: JourneySource) => source.reference ? `${source.label}: ${source.reference}` : source.label

const collapsedSources = (sources: JourneySource[]) => sources.reduce<{ text: string, count: number }[]>((groups, source) => {
    const text = sourceText(source)
    const last = groups[groups.length - 1]

    if (last && last.text === text) {
        last.count++
    } else {
        groups.push({ text, count: 1 })
    }

    return groups
}, [])

const money = (order: JourneyOrder) => order.currency_code
    ? String(locale.currencyFormat(order.currency_code, order.net_amount))
    : locale.number(order.net_amount)
</script>

<template>
    <div class="px-4 pb-8 pt-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-3xl text-sm text-gray-600">
                {{ ctrans("The latest orders placed on the website, with the traffic source of every visit that led to them. Open an order to see each visit and the pages the customer went through before buying.") }}
            </p>
            <SegmentedToggle v-model="selectedDays" :options="dayOptions" :ariaLabel="ctrans('Period')" />
        </div>

        <div class="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
            <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-6 2xl:grid-cols-12">
                <label class="flex min-w-0 flex-col gap-1 text-xs lg:col-span-3 2xl:col-span-2">
                    <span class="font-medium text-gray-600">{{ ctrans("Search") }}</span>
                    <IconField>
                        <InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
                        <InputText v-model="filters.search" class="h-9 w-full text-sm" :placeholder="ctrans('Order number or customer')" data-test="journey-search" />
                    </IconField>
                </label>
                <label class="flex min-w-0 flex-col gap-1 text-xs lg:col-span-3 2xl:col-span-2">
                    <span class="font-medium text-gray-600">{{ ctrans("Source") }}</span>
                    <MultiSelect
                        v-model="filters.sources"
                        class="h-9 w-full items-center text-sm"
                        :options="sourceOptions"
                        optionLabel="label"
                        optionValue="value"
                        :maxSelectedLabels="2"
                        :selectedItemsLabel="ctrans('{0} sources')"
                        :placeholder="ctrans('Any source')"
                        filter
                        data-test="journey-sources" />
                </label>
                <div class="flex min-w-0 flex-col gap-1 text-xs lg:col-span-2 2xl:col-span-2">
                    <span class="font-medium text-gray-600">{{ ctrans("Visits before ordering") }}</span>
                    <SegmentedToggle v-model="filters.visits" :options="visitOptions" :ariaLabel="ctrans('Visits before ordering')" class="journey-visits" data-test="journey-visits" />
                </div>
                <div class="flex min-w-0 flex-col gap-1 text-xs lg:col-span-2 2xl:col-span-3">
                    <span class="font-medium text-gray-600">
                        {{ ctrans("Order amount") }}<template v-if="data?.currency_code"> ({{ data.currency_code }})</template>
                    </span>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-1.5">
                        <InputNumber
                            v-model="amountDrafts.min"
                            v-bind="amountInputOptions"
                            class="w-full"
                            inputClass="h-9 w-full text-sm"
                            :min="0"
                            :placeholder="ctrans('Min')"
                            :ariaLabel="ctrans('Minimum order amount')"
                            data-test="journey-min"
                            @input="filters.min = amountFromInput($event)" />
                        <span class="text-gray-400">–</span>
                        <InputNumber
                            v-model="amountDrafts.max"
                            v-bind="amountInputOptions"
                            class="w-full"
                            inputClass="h-9 w-full text-sm"
                            :min="0"
                            :placeholder="ctrans('Max')"
                            :ariaLabel="ctrans('Maximum order amount')"
                            data-test="journey-max"
                            @input="filters.max = amountFromInput($event)" />
                    </div>
                </div>
                <label class="flex min-w-0 flex-col gap-1 text-xs sm:col-span-2 lg:col-span-2 2xl:col-span-3">
                    <span class="font-medium text-gray-600">{{ ctrans("Landing page") }}</span>
                    <InputText v-model="filters.landing" class="h-9 w-full text-sm" :placeholder="ctrans('Part of the address, e.g. /aromatherapy')" data-test="journey-landing" />
                </label>
            </div>
        </div>

        <div v-if="!data" class="space-y-3" aria-busy="true">
            <div class="h-4 w-64 animate-pulse rounded bg-gray-200" />
            <div class="h-96 w-full animate-pulse rounded bg-gray-100" />
        </div>

        <template v-else>
            <div ref="tableTop" class="mb-2 flex min-h-8 scroll-mt-4 flex-wrap items-center justify-between gap-2">
                <p class="flex items-center gap-1.5 text-sm text-gray-600" aria-live="polite" data-test="journey-count">
                    <FontAwesomeIcon v-if="hasFilters" icon="fal fa-filter" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                    {{ countText }}
                </p>
                <Button v-if="hasFilters" type="tertiary" size="s" icon="fal fa-times" :label="ctrans('Clear filters')" data-test="journey-clear" @click="clearFilters" />
            </div>

            <p v-if="!data.total" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                {{ ctrans("No tracked purchases in this period.") }}
            </p>

            <div v-else-if="!data.orders.length" class="flex flex-col items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500" data-test="journey-no-match">
                <FontAwesomeIcon icon="fal fa-filter" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                <p>{{ ctrans("No orders match these filters") }}</p>
                <Button type="tertiary" size="s" icon="fal fa-times" :label="ctrans('Clear filters')" @click="clearFilters" />
            </div>

            <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white transition-opacity" :class="{ 'opacity-50': isLoading }">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ ctrans("Order") }}</th>
                            <th class="px-4 py-2">{{ ctrans("Customer") }}</th>
                            <th class="px-4 py-2 text-right">{{ ctrans("Net") }}</th>
                            <th class="px-4 py-2">{{ ctrans("Order placed") }}</th>
                            <th class="px-4 py-2 text-right">{{ ctrans("Visits") }}</th>
                            <th class="px-4 py-2">{{ ctrans("Sources") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="order in data.orders"
                            :key="order.purchase_id"
                            class="cursor-pointer hover:bg-[--app-accent-soft] focus-visible:bg-[--app-accent-soft]"
                            tabindex="0"
                            @click="openJourney(order.purchase_id)"
                            @keydown.enter="openJourney(order.purchase_id)">
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ order.reference }}</td>
                            <td class="px-4 py-2">
                                <Link :href="route(order.customer.route.name, order.customer.route.parameters)" class="primaryLink" @click.stop>
                                    {{ order.customer.name }}
                                </Link>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">{{ money(order) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 tabular-nums text-gray-500">{{ useFormatTime(order.submitted_at, { formatTime: "hm" }) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ order.visits }}</td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap items-center gap-1">
                                    <span
                                        v-for="(group, index) in collapsedSources(order.sources)"
                                        :key="`${order.purchase_id}-${index}`"
                                        class="rounded border border-[--app-accent-muted] bg-[--app-accent-soft] px-1.5 text-xs text-gray-700">
                                        {{ group.text }}<template v-if="group.count > 1"> ×{{ group.count }}</template>
                                    </span>
                                    <span v-if="!order.sources.length" class="text-gray-400">-</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginator
                v-if="data.orders.length && data.matching > data.per_page"
                :rows="data.per_page"
                :totalRecords="data.matching"
                :first="(data.page - 1) * data.per_page"
                :pageLinkSize="3"
                template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
                class="mt-3"
                data-test="journey-paginator"
                @page="changePage" />
        </template>

        <Dialog v-model:visible="isDetailOpen" modal dismissableMask :header="ctrans('Order journey')" class="w-[min(1180px,96vw)]">
            <SeoDashboardJourneyDetail v-if="detail" :journey="detail" />
            <div v-else class="space-y-3" aria-busy="true">
                <div class="h-4 w-64 animate-pulse rounded bg-gray-200" />
                <div class="h-72 w-full animate-pulse rounded bg-gray-100" />
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.journey-visits {
    width: 100%;
    display: flex;
}

.journey-visits :deep(.p-togglebutton) {
    flex: 1 1 0;
}
</style>
