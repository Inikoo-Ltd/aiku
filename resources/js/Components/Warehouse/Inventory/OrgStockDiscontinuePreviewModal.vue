<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import { notify } from "@kyvg/vue3-notification"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { computed, inject, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExpandAlt, faCompressAlt, faChevronDown, faInfoCircle } from "@fal"
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
    webpages: { count: number; urls: string[] }
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

interface DetailItem {
    label: string
    value?: string | number
}

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
                items: Object.entries(preview.portfolios.by_platform).map(([platform, count]) => ({ label: capitalize(platform), value: count })),
            }
        case "external_shops":
            return {
                count: preview.external_shops.length,
                items: preview.external_shops.map((listing) => ({ label: `${listing.shop_name}: ${listing.code}`, value: listing.status })),
            }
        case "webpages":
            return { count: preview.webpages.count, items: preview.webpages.urls.map((url) => ({ label: url })) }
        default:
            return {
                count: preview.orders.count,
                summary: preview.orders.count ? ctrans(":quantity units", { quantity: locale.number(preview.orders.quantity) }) : undefined,
                items: referenceItems(preview.orders.references),
            }
    }
}

const expandableKeys = computed(() =>
    previews.value.flatMap((preview) => [
        ...(Object.keys(preview.organisations).length ? [`${preview.id}:organisations`] : []),
        ...detailColumns.value.filter((column) => detailCell(preview, column.key).items.length).map((column) => `${preview.id}:${column.key}`),
    ])
)
const allExpanded = computed(() => expandableKeys.value.length > 0 && expandableKeys.value.every((key) => expanded.value[key]))
const isExpanded = (preview: Preview, key: string) => !!expanded.value[`${preview.id}:${key}`]
const toggleExpanded = (preview: Preview, key: string) => {
    expanded.value[`${preview.id}:${key}`] = !isExpanded(preview, key)
}
const toggleAllExpanded = () => {
    expanded.value = allExpanded.value ? {} : Object.fromEntries(expandableKeys.value.map((key) => [key, true]))
}
</script>

<template>
    <Modal :isOpen="isOpen" :zIndex="zIndex" @onClose="emits('onClose')" :width="isFullscreen ? 'w-full' : 'w-full max-w-7xl'">
        <div class="flex flex-col gap-4" :class="{ 'h-[calc(100vh-5rem)]': isFullscreen }">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold">{{ ctrans("Discontinue preview") }}</h3>
                    <p class="text-sm text-gray-500">{{ ctrans("What still hangs off the selected SKOs. Nothing is changed yet.") }}</p>
                    <p class="text-xs text-gray-400">{{ ctrans("Customer stores are never touched: once discontinued, their own stock sync shows zero and they delist it themselves.") }}</p>
                </div>
                <button
                    v-tooltip="isFullscreen ? ctrans('Exit full screen') : ctrans('Full screen')"
                    type="button"
                    class="shrink-0 rounded-md border border-gray-300 bg-white px-2 py-1.5 text-gray-600 transition-colors hover:border-[--app-accent] hover:text-[--app-accent] focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]"
                    :aria-label="isFullscreen ? ctrans('Exit full screen') : ctrans('Full screen')"
                    :aria-pressed="isFullscreen"
                    @click="isFullscreen = !isFullscreen"
                >
                    <FontAwesomeIcon :icon="isFullscreen ? faCompressAlt : faExpandAlt" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div v-if="isLoading" class="flex items-center gap-2 py-8 justify-center text-gray-500" :class="{ 'flex-1': isFullscreen }">
                <LoadingIcon /> {{ ctrans("Loading") }}
            </div>

            <div v-else-if="errorMessage" class="text-red-600 text-sm" :class="{ 'flex-1': isFullscreen }">{{ errorMessage }}</div>

            <div v-else class="overflow-auto rounded-md border border-gray-200" :class="isFullscreen ? 'min-h-0 flex-1' : 'max-h-[65vh]'">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs text-gray-600 shadow-[0_1px_0_theme(colors.gray.200)]">
                        <tr>
                            <th class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    {{ ctrans("SKO") }}
                                    <button
                                        v-if="expandableKeys.length"
                                        type="button"
                                        class="rounded px-1.5 py-0.5 font-medium text-[--app-accent] transition-colors hover:bg-[--app-accent-soft]"
                                        @click="toggleAllExpanded"
                                    >
                                        {{ allExpanded ? ctrans("Hide all details") : ctrans("Show all details") }}
                                    </button>
                                </div>
                            </th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Stock") }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Cover") }}</th>
                            <th v-for="column in detailColumns" :key="column.key" class="px-3 py-2" :title="column.title">{{ column.label }}</th>
                            <th class="px-3 py-2">{{ ctrans("Flags") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="preview in previews" :key="preview.id" class="border-b border-gray-100 align-top last:border-b-0">
                            <td class="min-w-[15rem] px-3 py-2">
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
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ locale.number(preview.quantity) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                                {{ preview.days_of_cover === null ? "-" : ctrans(":days days", { days: preview.days_of_cover }) }}
                            </td>
                            <td v-for="column in detailColumns" :key="column.key" class="max-w-[15rem] px-3 py-2">
                                <span v-if="!detailCell(preview, column.key).count" class="text-gray-400">0</span>
                                <template v-else>
                                    <component
                                        :is="detailCell(preview, column.key).items.length ? 'button' : 'div'"
                                        :type="detailCell(preview, column.key).items.length ? 'button' : undefined"
                                        class="flex items-center gap-1.5 whitespace-nowrap text-left"
                                        :class="{ 'rounded transition-colors hover:text-[--app-accent]': detailCell(preview, column.key).items.length }"
                                        :aria-expanded="detailCell(preview, column.key).items.length ? isExpanded(preview, column.key) : undefined"
                                        :aria-label="detailCell(preview, column.key).items.length ? ctrans('Show :column', { column: column.label }) : undefined"
                                        @click="detailCell(preview, column.key).items.length && toggleExpanded(preview, column.key)"
                                    >
                                        <span class="font-semibold text-amber-700">{{ detailCell(preview, column.key).count }}</span>
                                        <span v-if="detailCell(preview, column.key).summary" class="text-xs text-gray-500">{{ detailCell(preview, column.key).summary }}</span>
                                        <FontAwesomeIcon
                                            v-if="detailCell(preview, column.key).items.length"
                                            :icon="faChevronDown"
                                            class="text-[10px] text-gray-400 transition-transform"
                                            :class="{ 'rotate-180': isExpanded(preview, column.key) }"
                                            aria-hidden="true"
                                        />
                                    </component>
                                    <ul v-if="isExpanded(preview, column.key)" class="mt-1 min-w-[11rem] list-disc space-y-1 pl-4 text-xs text-gray-600">
                                        <li v-for="(item, index) in detailCell(preview, column.key).items" :key="index">
                                            <span class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                                <span class="break-words">{{ item.label }}</span>
                                                <span v-if="item.value !== undefined" class="whitespace-nowrap font-medium tabular-nums text-gray-800">{{ item.value }}</span>
                                            </span>
                                        </li>
                                    </ul>
                                </template>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">
                                <div v-if="preview.is_exclusive" class="text-purple-700">{{ ctrans("Exclusive range") }}</div>
                                <div v-if="!preview.number_products">{{ ctrans("No products") }}</div>
                                <div v-if="!preview.mailshots.known" v-tooltip="preview.mailshots.reason">{{ ctrans("Mailshots: check by hand") }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="discontinueRoute && !isLoading && previews.length" class="grid gap-3 border-t border-gray-200 pt-4 md:grid-cols-3">
                <label class="text-sm">
                    <span class="block text-gray-500 mb-1">{{ canChangeGroup ? ctrans("New state, every organisation") : ctrans("New state") }}</span>
                    <select v-model="form.state" class="w-full rounded-md border-gray-300 text-sm">
                        <option v-for="option in stateOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">{{ selectedMeaning }}</p>
                </label>
                <label class="text-sm">
                    <span class="block text-gray-500 mb-1">{{ ctrans("Effective from (empty = now)") }}</span>
                    <input v-model="form.effective_at" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                </label>
                <div class="text-sm">
                    <span class="mb-1 inline-flex items-center gap-1.5 rounded bg-[--app-accent-soft] px-2 py-1 font-medium text-gray-900">
                        <FontAwesomeIcon :icon="faInfoCircle" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        {{ canChangeGroup ? ctrans("Applies to every organisation carrying this product") : ctrans("Applies to :organisation only", { organisation: homeOrganisation }) }}
                    </span>
                    <div v-if="canChangeGroup" class="flex flex-wrap gap-2">
                        <label v-for="code in changeableOrganisationCodes" :key="code" class="flex items-center gap-1">
                            <span class="font-medium">{{ code }}</span>
                            <select v-model="form.organisation_states[code]" class="rounded-md border-gray-300 text-xs">
                                <option value="">{{ ctrans("follow") }}</option>
                                <option v-for="option in stateOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="md:col-span-3 text-sm">
                    <span class="block text-gray-500 mb-1">
                        {{ ctrans("Reason") }}
                        <span v-if="form.state === 'active'" class="text-gray-400">{{ ctrans("(optional)") }}</span>
                        <span v-else v-tooltip="ctrans('This is required')" class="cursor-help font-semibold text-red-600" :aria-label="ctrans('This is required')">*</span>
                    </span>
                    <PureTextarea v-model="form.reason" :rows="2" full :placeholder="ctrans('Why this SKO changes state')" />
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
