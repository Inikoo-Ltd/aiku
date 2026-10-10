<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { notify } from "@kyvg/vue3-notification"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { computed, inject, ref, watch } from "vue"
import { DatePicker, InputText, Popover, Select, Textarea } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import type { IconDefinition } from "@fortawesome/fontawesome-svg-core"
import { faExpandAlt, faCompressAlt, faChevronDown, faInfoCircle, faSeedling, faCheck, faBan, faExclamationTriangle, faTimes, faClock, faHammer, faCheckCircle, faBroadcastTower, faSkull } from "@fal"
import { capitalize } from "@/Composables/capitalize"

interface CountWithReferences {
    count: number
    references: string[]
}

interface Preview {
    id: number
    code: string
    name: string | null
    state: string
    state_label: string
    organisation: string
    updated_at: string | null
    organisations: Record<string, string>
    quantity: number
    days_of_cover: number | null
    number_products: number
    purchase_orders: CountWithReferences
    stock_deliveries: CountWithReferences
    restock_requests: number
    portfolios: { count: number; customers: number; by_platform: Record<string, number> }
    external_shops: { code: string; status: string; shop_code: string; shop_name: string }[]
    webpages: { count: number; urls: string[]; pages?: { url: string; state: string; canonical_url: string | null }[] }
    mailshots: { known: boolean; reason: string }
    orders: CountWithReferences & { quantity: number }
    is_exclusive: boolean
    can_change_group: boolean
    changeable_organisations: string[]
}

const props = defineProps<{
    isOpen: boolean
    orgStockIds: number[]
    previewRoute: routeType
    discontinueRoute?: routeType | null
    initialState?: string
    zIndex?: number
}>()

const emits = defineEmits<{ (e: "onClose"): void; (e: "onDone"): void }>()

const stateOptions = computed(() => [
    { value: "active", label: ctrans("Active"), meaning: ctrans("Normal ordering and selling") },
    { value: "suspended", label: ctrans("Hold"), meaning: ctrans("Stops ordering; sells what is left") },
    { value: "discontinuing", label: ctrans("Discontinued"), meaning: ctrans("No ordering; sells until stock runs out") },
    { value: "discontinued", label: ctrans("Retired"), meaning: ctrans("Archived; not for sale") },
])

const stateLabel = (value: string) => stateOptions.value.find((option) => option.value === value)?.label ?? value

const form = ref({
    state: "discontinuing",
    reason: "",
    effective_at: "",
    organisation_states: {} as Record<string, string>,
})
const isSubmitting = ref(false)
const isFullscreen = ref(false)
const submitError = ref<string | null>(null)

const organisationCodes = computed(() => {
    const codes = new Set<string>()
    previews.value.forEach((preview) => Object.keys(preview.organisations).forEach((code) => codes.add(code)))
    return Array.from(codes).sort()
})

const canChangeGroup = computed(() => previews.value.length > 0 && previews.value.every((preview) => preview.can_change_group))
const scope = computed(() => (canChangeGroup.value ? "group" : "organisation"))
const homeOrganisation = computed(() => previews.value[0]?.organisation ?? "")

const changeableOrganisationCodes = computed(() => {
    if (!previews.value.length) return []
    return organisationCodes.value.filter((code) => previews.value.every((preview) => preview.changeable_organisations.includes(code)))
})

const newStateFor = (preview: Preview) => {
    if (scope.value === "organisation") return form.value.state
    return form.value.organisation_states[preview.organisation] || form.value.state
}

const followState = "follow"

const organisationStateOptions = computed(() => [{ value: followState, label: ctrans("Follow") }, ...stateOptions.value])

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const toLocalIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const effectiveDate = computed<Date | null>({
    get: () => {
        if (!form.value.effective_at) return null
        const [year, month, day] = form.value.effective_at.split("-").map(Number)
        return new Date(year, month - 1, day)
    },
    set: (date) => {
        form.value.effective_at = date ? toLocalIsoDate(date) : ""
    },
})

const stateCopy = computed<Record<string, { title: string; note: string }>>(() => ({
    active: {
        title: ctrans("Reactivate preview"),
        note: ctrans("Ordering and selling go back to normal."),
    },
    suspended: {
        title: ctrans("Hold preview"),
        note: ctrans("No new ordering. Customer stores are never touched and keep selling what is left in stock."),
    },
    discontinuing: {
        title: ctrans("Discontinue preview"),
        note: ctrans("Customer stores are never touched: their own stock sync counts down to zero and they delist it themselves."),
    },
    discontinued: {
        title: ctrans("Retire preview"),
        note: ctrans("Customer stores are never touched: their own stock sync shows zero straight away and they delist it themselves."),
    },
}))

const selectedCopy = computed(() => stateCopy.value[form.value.state] ?? stateCopy.value.discontinuing)

const selectedMeaning = computed(() => stateOptions.value.find((option) => option.value === form.value.state)?.meaning ?? "")

const canConfirm = computed(() =>
    !!props.discontinueRoute && previews.value.length > 0 && (form.value.state === "active" || form.value.reason.trim().length > 0)
)

const onConfirm = () => {
    if (!props.discontinueRoute || !canConfirm.value) return
    isSubmitting.value = true
    submitError.value = null
    const organisation_states = canChangeGroup.value
        ? Object.fromEntries(Object.entries(form.value.organisation_states).filter(([, state]) => state))
        : {}
    router.post(
        route(props.discontinueRoute.name, props.discontinueRoute.parameters),
        {
            org_stock_ids: props.orgStockIds,
            state: form.value.state,
            scope: scope.value,
            reason: form.value.reason || null,
            effective_at: form.value.effective_at || null,
            organisation_states,
            expected_updated_at: Object.fromEntries(previews.value.map((preview) => [preview.id, preview.updated_at])),
            source: "ui",
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                notify({ title: ctrans("Done"), text: ctrans("SKO state updated"), type: "success" })
                emits("onDone")
                router.reload()
            },
            onError: (errors) => {
                submitError.value = Object.values(errors).flat().join(" ")
            },
            onFinish: () => {
                isSubmitting.value = false
            },
        }
    )
}

const locale = inject("locale", aikuLocaleStructure)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const previews = ref<Preview[]>([])
const expanded = ref<Record<string, boolean>>({})

const loadPreview = async () => {
    isLoading.value = true
    errorMessage.value = null
    previews.value = []
    expanded.value = {}
    try {
        const response = await axios.get(route(props.previewRoute.name, props.previewRoute.parameters), {
            params: { org_stock_ids: props.orgStockIds },
        })
        previews.value = response.data
        if (isFullscreen.value) {
            setAllExpanded(true)
        }
    } catch (error) {
        errorMessage.value = ctrans("Could not load the preview")
    } finally {
        isLoading.value = false
    }
}

watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        form.value.state = props.initialState ?? "discontinuing"
        loadPreview()
    }
}, { immediate: true })

interface DetailIcon {
    icon: IconDefinition
    class: string
    tooltip: string
}

interface DetailItem {
    label: string
    tooltip?: string
    value?: string | number
    logo?: string
    href?: string
    labelIcon?: DetailIcon
    valueIcon?: DetailIcon
}

const webpageStateIcons = computed<Record<string, DetailIcon>>(() => ({
    in_process: { icon: faHammer, class: "text-amber-500", tooltip: ctrans("In construction") },
    ready: { icon: faCheckCircle, class: "text-blue-500", tooltip: ctrans("Ready") },
    live: { icon: faBroadcastTower, class: "text-green-600", tooltip: ctrans("Live") },
    closed: { icon: faSkull, class: "text-red-500", tooltip: ctrans("Closed") },
}))

const platformsWithLogo = ["allegro", "amazon", "ebay", "magento", "manual", "shopify", "tiktok", "wix", "woocommerce"]

const platformLogo = (platform: string) => (platformsWithLogo.includes(platform.toLowerCase()) ? `/assets/channel_logo/${platform.toLowerCase()}.svg` : undefined)

const productStatusIcons = computed<Record<string, DetailIcon>>(() => ({
    in_process: { icon: faSeedling, class: "text-lime-500", tooltip: ctrans("In process") },
    "for-sale": { icon: faCheck, class: "text-emerald-500", tooltip: ctrans("For Sale") },
    "not-for-sale": { icon: faBan, class: "text-gray-500", tooltip: ctrans("Not For Sale") },
    "out-of-stock": { icon: faExclamationTriangle, class: "text-orange-500", tooltip: ctrans("Out of Stock") },
    discontinued: { icon: faTimes, class: "text-red-500", tooltip: ctrans("Discontinued") },
    "coming-soon": { icon: faClock, class: "text-yellow-500", tooltip: ctrans("Coming Soon") },
}))

interface DetailCell {
    count: number
    summary?: string
    items: DetailItem[]
}

const stateClasses: Record<string, string> = {
    active: "bg-green-50 text-green-700",
    suspended: "bg-amber-50 text-amber-700",
    discontinuing: "bg-red-50 text-red-700",
    discontinued: "bg-gray-200 text-gray-700",
}

const detailColumns = computed(() => [
    { key: "purchase_orders", label: ctrans("Purchase orders"), title: undefined },
    { key: "stock_deliveries", label: ctrans("Deliveries"), title: undefined },
    { key: "restock_requests", label: ctrans("Restock requests"), title: ctrans("Open warehouse restock requests, removed when the SKO is discontinued") },
    { key: "portfolios", label: ctrans("Portfolios"), title: undefined },
    { key: "external_shops", label: ctrans("Marketplaces"), title: undefined },
    { key: "webpages", label: ctrans("Web pages"), title: undefined },
    { key: "orders", label: ctrans("Customer orders"), title: undefined },
])

interface DetailColumn {
    key: string
    label: string
    title: string | undefined
}

interface DetailColumnGroup {
    key: string
    label: string
    title: string | undefined
    columns: DetailColumn[]
}

const columnsByKey = (keys: string[]) => detailColumns.value.filter((column) => keys.includes(column.key))

const columnGroups = computed<DetailColumnGroup[]>(() => {
    if (isFullscreen.value) {
        return detailColumns.value.map((column) => ({ ...column, columns: [column] }))
    }

    const incoming = columnsByKey(["purchase_orders", "stock_deliveries", "restock_requests"])
    const channels = columnsByKey(["portfolios", "external_shops", "webpages"])

    return [
        { key: "incoming", label: ctrans("Incoming"), title: incoming.map((column) => column.label).join(", "), columns: incoming },
        { key: "channels", label: ctrans("Channels"), title: channels.map((column) => column.label).join(", "), columns: channels },
        ...columnsByKey(["orders"]).map((column) => ({ ...column, columns: [{ ...column, label: ctrans("Open orders") }] })),
    ]
})

const compactColumnKeys = ["purchase_orders", "stock_deliveries", "restock_requests"]

const groupWidthClass = (group: DetailColumnGroup) => {
    if (!isFullscreen.value) {
        return group.columns.length > 1 ? "w-48 max-w-[12rem]" : "w-36 max-w-[10rem]"
    }
    return compactColumnKeys.includes(group.key) ? "w-px" : "w-40 max-w-[10rem]"
}

const coverLabel = (preview: Preview) => (preview.days_of_cover === null ? "-" : ctrans(":days days", { days: preview.days_of_cover }))

const referenceItems = (references: string[]): DetailItem[] => references.map((reference) => ({ label: reference }))

const detailCell = (preview: Preview, key: string): DetailCell => {
    switch (key) {
        case "purchase_orders":
            return { count: preview.purchase_orders.count, items: referenceItems(preview.purchase_orders.references) }
        case "stock_deliveries":
            return { count: preview.stock_deliveries.count, items: referenceItems(preview.stock_deliveries.references) }
        case "restock_requests":
            return { count: preview.restock_requests, summary: preview.restock_requests ? ctrans("will be removed") : undefined, items: [] }
        case "portfolios":
            return {
                count: preview.portfolios.count,
                summary: preview.portfolios.count ? ctrans(":count customers", { count: preview.portfolios.customers }) : undefined,
                items: Object.entries(preview.portfolios.by_platform).map(([platform, count]) => ({ label: capitalize(platform), value: count, logo: platformLogo(platform) })),
            }
        case "external_shops":
            return {
                count: preview.external_shops.length,
                items: preview.external_shops.map((listing) => ({
                    label: `${listing.shop_code}: ${listing.code}`,
                    tooltip: `${listing.shop_name}: ${listing.code}`,
                    value: listing.status,
                    valueIcon: productStatusIcons.value[listing.status],
                })),
            }
        case "webpages":
            if (!preview.webpages.pages) {
                return { count: preview.webpages.count, items: preview.webpages.urls.map((url) => ({ label: `/${url}` })) }
            }
            return {
                count: preview.webpages.pages.length,
                items: preview.webpages.pages.map((page) => ({
                    label: `/${page.url}`,
                    href: page.canonical_url ?? undefined,
                    labelIcon: webpageStateIcons.value[page.state],
                })),
            }
        default:
            return {
                count: preview.orders.count,
                summary: preview.orders.count ? ctrans(":quantity units", { quantity: locale.number(preview.orders.quantity) }) : undefined,
                items: referenceItems(preview.orders.references),
            }
    }
}

const popoverColumnKeys = ["purchase_orders", "stock_deliveries"]
const isPopoverColumn = (key: string) => popoverColumnKeys.includes(key)

const referencePopover = ref()
const isReferencePopoverOpen = ref(false)
const referencePopoverKey = ref<string | null>(null)
const referencePopoverTitle = ref("")
const referencePopoverItems = ref<string[]>([])

const openReferencePopover = (event: MouseEvent, preview: Preview, column: { key: string; label: string }) => {
    if (!isReferencePopoverOpen.value) {
        referencePopoverKey.value = `${preview.id}:${column.key}`
        referencePopoverTitle.value = column.label
        referencePopoverItems.value = detailCell(preview, column.key).items.map((item) => item.label)
    }
    referencePopover.value.toggle(event)
}

const isReferencePopoverOpenFor = (preview: Preview, key: string) => isReferencePopoverOpen.value && referencePopoverKey.value === `${preview.id}:${key}`

const splitReference = (reference: string) => {
    const [head, ...rest] = reference.split(" - ")
    return { head, tail: rest.join(" - ") }
}

const onDetailClick = (event: MouseEvent, preview: Preview, column: { key: string; label: string }) => {
    if (!detailCell(preview, column.key).items.length) return
    if (isPopoverColumn(column.key)) openReferencePopover(event, preview, column)
    else toggleExpanded(preview, column.key)
}

const expandableKeys = computed(() =>
    previews.value.flatMap((preview) => [
        ...(Object.keys(preview.organisations).length ? [`${preview.id}:organisations`] : []),
        ...detailColumns.value
            .filter((column) => !isPopoverColumn(column.key) && detailCell(preview, column.key).items.length)
            .map((column) => `${preview.id}:${column.key}`),
    ])
)
const isExpanded = (preview: Preview, key: string) => !!expanded.value[`${preview.id}:${key}`]
const toggleExpanded = (preview: Preview, key: string) => {
    expanded.value[`${preview.id}:${key}`] = !isExpanded(preview, key)
}
const setAllExpanded = (shouldExpand: boolean) => {
    expanded.value = shouldExpand ? Object.fromEntries(expandableKeys.value.map((key) => [key, true])) : {}
}

watch(isFullscreen, setAllExpanded)
</script>

<template>
    <Modal :isOpen="isOpen" :zIndex="zIndex" @onClose="emits('onClose')" :width="isFullscreen ? 'w-full' : 'w-full max-w-7xl'">
        <div class="flex max-h-[calc(100dvh-5rem)] flex-col gap-4" :class="{ 'h-[calc(100dvh-5rem)]': isFullscreen }">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold">{{ selectedCopy.title }}</h3>
                    <p class="text-sm text-gray-500">{{ ctrans("What still hangs off the selected SKOs. Nothing is changed yet.") }}</p>
                    <p class="text-xs text-gray-400">{{ selectedCopy.note }}</p>
                </div>
                <button
                    v-tooltip="isFullscreen ? ctrans('Exit full screen') : ctrans('Full screen with all details')"
                    type="button"
                    class="flex shrink-0 items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm font-medium text-gray-600 transition-colors hover:border-[--app-accent] hover:text-[--app-accent] focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]"
                    :aria-pressed="isFullscreen"
                    @click="isFullscreen = !isFullscreen"
                >
                    {{ ctrans("Full Details") }}
                    <FontAwesomeIcon :icon="isFullscreen ? faCompressAlt : faExpandAlt" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div v-if="isLoading" class="flex items-center gap-2 py-8 justify-center text-gray-500" :class="{ 'flex-1': isFullscreen }">
                <LoadingIcon /> {{ ctrans("Loading") }}
            </div>

            <div v-else-if="errorMessage" class="text-red-600 text-sm" :class="{ 'flex-1': isFullscreen }">{{ errorMessage }}</div>

            <div v-else class="min-h-0 flex-1 overflow-auto rounded-md border border-gray-200" :class="{ 'max-h-[65vh]': !isFullscreen }">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs text-gray-600 shadow-[0_1px_0_theme(colors.gray.200)]">
                        <tr>
                            <th class="px-2 py-2">
                                {{ ctrans("SKO") }}
                            </th>
                            <th class="w-px whitespace-nowrap px-2 py-2 text-right">{{ ctrans("Stock / Days covered") }}</th>
                            <th v-for="group in columnGroups" :key="group.key" class="px-2 py-2" :class="groupWidthClass(group)" :title="group.title">{{ group.label }}</th>
                            <th class="px-2 py-2" :class="isFullscreen && 'w-40'">{{ ctrans("Flags") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="preview in previews" :key="preview.id" class="border-b border-gray-100 align-top last:border-b-0">
                            <td class="min-w-[10rem] px-2 py-2" :class="!isFullscreen && 'w-52'">
                                <div class="font-medium">{{ preview.code }} — {{ preview.name }}</div>
                                <div class="mt-1 flex items-center gap-1.5 whitespace-nowrap text-xs font-semibold">
                                    <span class="rounded px-1.5 py-0.5" :class="stateClasses[preview.state]">{{ stateLabel(preview.state) }}</span>
                                    <span class="text-gray-400" aria-hidden="true">→</span>
                                    <span class="rounded px-1.5 py-0.5" :class="stateClasses[newStateFor(preview)]">{{ stateLabel(newStateFor(preview)) }}</span>
                                </div>
                                <template v-if="Object.keys(preview.organisations).length">
                                    <button
                                        type="button"
                                        class="mt-1.5 flex items-center gap-1 text-xs text-gray-500 transition-colors hover:text-[--app-accent]"
                                        :aria-expanded="isExpanded(preview, 'organisations')"
                                        @click="toggleExpanded(preview, 'organisations')"
                                    >
                                        {{ ctrans("Current organisation status") }}
                                        <FontAwesomeIcon :icon="faChevronDown" class="text-[10px] transition-transform" :class="{ 'rotate-180': isExpanded(preview, 'organisations') }" aria-hidden="true" />
                                    </button>
                                    <ul v-if="isExpanded(preview, 'organisations')" class="mt-1 space-y-1 text-xs">
                                        <li v-for="(state, organisation) in preview.organisations" :key="organisation" class="flex items-center gap-2">
                                            <span class="w-14 font-medium text-gray-700">{{ organisation }}:</span>
                                            <span class="rounded px-1.5 py-0.5 font-semibold" :class="stateClasses[state]">{{ stateLabel(state) }}</span>
                                        </li>
                                    </ul>
                                </template>
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">
                                <div>{{ locale.number(preview.quantity) }}</div>
                                <div v-tooltip="ctrans('Days covered')" class="text-xs text-gray-500">{{ coverLabel(preview) }}</div>
                            </td>
                            <td v-for="group in columnGroups" :key="group.key" class="px-2 py-2" :class="groupWidthClass(group)">
                              <div
                                v-for="column in group.columns"
                                :key="column.key"
                                :class="!isFullscreen && '[&:not(:first-child)]:mt-1'"
                              >
                                <span v-if="!detailCell(preview, column.key).count && isFullscreen" class="text-gray-400">0</span>
                                <template v-else>
                                    <component
                                        :is="detailCell(preview, column.key).items.length ? 'button' : 'div'"
                                        :type="detailCell(preview, column.key).items.length ? 'button' : undefined"
                                        v-tooltip="detailCell(preview, column.key).summary"
                                        class="group flex items-center gap-1.5 whitespace-nowrap text-left"
                                        :class="[
                                            !isFullscreen && 'w-full',
                                            detailCell(preview, column.key).items.length && 'rounded transition-colors hover:text-[--app-accent]',
                                        ]"
                                        :aria-expanded="detailCell(preview, column.key).items.length ? (isPopoverColumn(column.key) ? isReferencePopoverOpenFor(preview, column.key) : isExpanded(preview, column.key)) : undefined"
                                        :aria-label="detailCell(preview, column.key).items.length ? ctrans('Show :column', { column: column.label }) : undefined"
                                        @click="onDetailClick($event, preview, column)"
                                    >
                                        <span
                                            v-if="!isFullscreen"
                                            class="mr-auto truncate text-xs font-semibold text-gray-700"
                                            :class="detailCell(preview, column.key).items.length && 'transition-colors group-hover:text-[--app-accent]'"
                                            :title="column.title"
                                            >{{ column.label }}</span
                                        >
                                        <span :class="detailCell(preview, column.key).count ? 'font-semibold text-amber-700' : 'text-gray-400'">{{ detailCell(preview, column.key).count }}</span>
                                        <FontAwesomeIcon
                                            v-if="detailCell(preview, column.key).items.length || !isFullscreen"
                                            :icon="faChevronDown"
                                            class="text-[10px] text-gray-400 transition-transform"
                                            :class="{
                                                invisible: !detailCell(preview, column.key).items.length,
                                                'rotate-180': isPopoverColumn(column.key) ? isReferencePopoverOpenFor(preview, column.key) : isExpanded(preview, column.key),
                                            }"
                                            aria-hidden="true"
                                        />
                                    </component>
                                    <ul
                                        v-if="column.key === 'portfolios' && isExpanded(preview, column.key)"
                                        class="mb-1 mt-1 flex max-h-24 w-full flex-wrap gap-1 overflow-y-auto border-l-2 border-gray-200 pl-2 pr-1 text-[11px] [scrollbar-width:thin]"
                                    >
                                        <li
                                            v-for="(item, index) in detailCell(preview, column.key).items"
                                            :key="index"
                                            v-tooltip="item.label"
                                            class="inline-flex items-center gap-0.5 rounded-full border border-gray-200 bg-gray-50 px-1 leading-4"
                                        >
                                            <img v-if="item.logo" :src="item.logo" :alt="item.label" class="h-3 w-3 shrink-0 object-contain" />
                                            <span v-else class="text-gray-600">{{ item.label }}</span>
                                            <span class="font-medium tabular-nums text-gray-800">{{ item.value }}</span>
                                        </li>
                                    </ul>
                                    <ul
                                        v-else-if="!isPopoverColumn(column.key) && isExpanded(preview, column.key)"
                                        class="mb-1 mt-1 max-h-24 w-full min-w-[6rem] space-y-1 overflow-y-auto border-l-2 border-gray-200 pl-2 pr-1 text-xs text-gray-500 [scrollbar-width:thin]"
                                    >
                                        <li v-for="(item, index) in detailCell(preview, column.key).items" :key="index" class="flex h-4 items-center gap-1.5">
                                            <img v-if="item.logo" v-tooltip="item.label" :src="item.logo" :alt="item.label" class="h-4 w-4 shrink-0 object-contain" />
                                            <FontAwesomeIcon
                                                v-if="item.labelIcon"
                                                v-tooltip="item.labelIcon.tooltip"
                                                :icon="item.labelIcon.icon"
                                                :class="item.labelIcon.class"
                                                class="shrink-0"
                                                fixed-width
                                                :aria-label="item.labelIcon.tooltip"
                                            />
                                            <a
                                                v-if="!item.logo && item.href"
                                                v-tooltip="item.tooltip ?? item.label"
                                                :href="item.href"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="min-w-0 truncate text-[--app-accent] hover:underline"
                                                >{{ item.label }}</a
                                            >
                                            <span v-else-if="!item.logo" v-tooltip="item.tooltip ?? item.label" class="min-w-0 truncate">{{ item.label }}</span>
                                            <FontAwesomeIcon
                                                v-if="item.valueIcon"
                                                v-tooltip="item.valueIcon.tooltip"
                                                :icon="item.valueIcon.icon"
                                                :class="item.valueIcon.class"
                                                class="shrink-0"
                                                fixed-width
                                                :aria-label="item.valueIcon.tooltip"
                                            />
                                            <span v-else-if="item.value !== undefined" class="ml-auto shrink-0 whitespace-nowrap font-medium tabular-nums text-gray-800">{{ item.value }}</span>
                                        </li>
                                    </ul>
                                </template>
                              </div>
                            </td>
                            <td class="px-2 py-2 text-xs text-gray-500">
                                <div v-if="preview.is_exclusive" class="text-purple-700">{{ ctrans("Exclusive range") }}</div>
                                <div v-if="!preview.number_products">{{ ctrans("No products") }}</div>
                                <div v-if="!preview.mailshots.known" v-tooltip="preview.mailshots.reason">{{ ctrans("Mailshots: check by hand") }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Popover ref="referencePopover" @show="isReferencePopoverOpen = true" @hide="isReferencePopoverOpen = false">
                    <div class="w-72 text-xs">
                        <p class="mb-1.5 font-medium text-gray-700">{{ referencePopoverTitle }}</p>
                        <ul class="max-h-24 space-y-1 overflow-y-auto pr-1 [scrollbar-width:thin]">
                            <li v-for="reference in referencePopoverItems" :key="reference" v-tooltip="reference" class="flex h-4 min-w-0 items-center gap-1.5">
                                <span class="shrink-0 font-medium text-gray-800">{{ splitReference(reference).head }}</span>
                                <span v-if="splitReference(reference).tail" class="truncate text-gray-400">{{ splitReference(reference).tail }}</span>
                            </li>
                        </ul>
                    </div>
                </Popover>
            </div>

            <div v-if="discontinueRoute && !isLoading && previews.length" class="grid gap-3 md:grid-cols-3">
                <div class="text-sm">
                    <label for="discontinue-new-state" class="block text-gray-500 mb-1">{{ canChangeGroup ? ctrans("New state, every organisation") : ctrans("New state") }}</label>
                    <Select v-model="form.state" inputId="discontinue-new-state" :options="stateOptions" optionLabel="label" optionValue="value" class="w-full" :class="fieldFocusClass" :pt="{ label: { class: '!text-sm' } }" />
                    <p class="text-xs text-gray-400 mt-1">{{ selectedMeaning }}</p>
                </div>
                <div class="text-sm">
                    <label for="discontinue-effective-at" class="block text-gray-500 mb-1">{{ ctrans("Effective from (empty = now)") }}</label>
                    <DatePicker
                        v-model="effectiveDate"
                        inputId="discontinue-effective-at"
                        dateFormat="d M yy"
                        :manualInput="false"
                        showIcon
                        iconDisplay="input"
                        showButtonBar
                        fluid
                        :placeholder="ctrans('Now')"
                        :class="fieldFocusClass"
                        :pt="{ pcInputText: { root: { class: '!text-sm' } } }"
                    />
                </div>
                <div class="text-sm">
                    <span class="mb-1 block text-gray-500">{{ ctrans("Per organisation") }}</span>
                    <div v-if="canChangeGroup" class="grid gap-2" :class="changeableOrganisationCodes.length > 1 && 'sm:grid-cols-2'">
                        <div v-for="code in changeableOrganisationCodes" :key="code" class="flex items-center gap-2">
                            <label :for="`discontinue-state-${code}`" class="w-12 shrink-0 font-medium text-gray-700">{{ code }}</label>
                            <Select
                                :modelValue="form.organisation_states[code] || followState"
                                :inputId="`discontinue-state-${code}`"
                                :options="organisationStateOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="min-w-0 flex-1"
                                :class="fieldFocusClass"
                                :pt="{ label: { class: '!text-sm' } }"
                                @update:modelValue="(state: string) => (form.organisation_states[code] = state === followState ? '' : state)"
                            />
                        </div>
                    </div>
                    <InputText v-else :modelValue="homeOrganisation" disabled fluid class="!text-sm" :aria-label="ctrans('Organisation')" />
                    <p class="mt-1 flex items-center gap-1 text-xs text-gray-400">
                        <FontAwesomeIcon :icon="faInfoCircle" fixed-width aria-hidden="true" />
                        {{ canChangeGroup ? ctrans("Applies to every organisation carrying this product") : ctrans("Applies to :organisation only", { organisation: homeOrganisation }) }}
                    </p>
                </div>
                <div class="md:col-span-3 text-sm">
                    <span class="block text-gray-500 mb-1">
                        {{ ctrans("Reason") }}
                        <span v-if="form.state === 'active'" class="text-gray-400">{{ ctrans("(optional)") }}</span>
                        <span v-else v-tooltip="ctrans('This is required')" class="cursor-help font-semibold text-red-600" :aria-label="ctrans('This is required')">*</span>
                    </span>
                    <Textarea v-model="form.reason" :rows="2" autoResize fluid :placeholder="ctrans('Why this SKO changes state')" class="!text-sm focus:!border-[--app-accent]" />
                </div>
                <div v-if="submitError" class="md:col-span-3 text-sm text-red-600">{{ submitError }}</div>
            </div>

            <div class="flex justify-end gap-2">
                <Button type="cancel" :label="ctrans('Close')" @click="emits('onClose')" />
                <Button type="negative" :label="ctrans('Confirm')" :disabled="!canConfirm" :loading="isSubmitting" @click="onConfirm" />
            </div>
        </div>
    </Modal>
</template>
