<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Dialog from "primevue/dialog"
import { nextTick, onMounted, onUnmounted, ref, watch } from "vue"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { routeType } from "@/types/route"
import axios from "axios"
import { debounce } from "lodash-es"
import { faSearch, faSpinner } from "@fal"
import { faExclamationTriangle, faMinus, faPlus } from "@fas"
import { notify } from "@kyvg/vue3-notification"
import { ctrans as trans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import Image from "@common/Components/Image.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import StockCoverLabel from "@/Components/Procurement/StockCoverLabel.vue"

library.add(faSearch, faPlus, faMinus, faSpinner, faExclamationTriangle)

const props = defineProps<{
    fetchRoute: routeType
}>()

const model = defineModel<boolean>()
const rows = ref<any[]>([])
const optionsLinks = ref<any>(null)
const isLoading = ref(false)
const isLoadingMore = ref(false)
let latestRequestId = 0
const isRowLoading = ref<number | null>(null)
const searchQuery = ref("")
const overBudgetMessage = ref<string | null>(null)
const currency = ref<string>("")
const locale = useLocaleStore()
const contentRef = ref<HTMLElement | null>(null)

const getUrlFetch = (additionalParams: {}) => {
    return route(props.fetchRoute.name, {
        ...props.fetchRoute.parameters,
        ...additionalParams,
    })
}

const fetchRows = async (url?: string, append = false) => {
    const loadingFlag = append ? isLoadingMore : isLoading
    const requestId = append ? latestRequestId : ++latestRequestId
    loadingFlag.value = true
    const urlToFetch = url || getUrlFetch({})

    try {
        const response = await axios.get(urlToFetch)
        if (requestId !== latestRequestId) {
            return
        }
        rows.value = append ? [...rows.value, ...response.data.data] : response.data.data
        overBudgetMessage.value = response.data.over_budget_message ?? null
        currency.value = response.data.currency ?? currency.value
        optionsLinks.value = { next: response.data.links?.next ?? response.data.next_page_url }
    } catch (error) {
        console.error("Error fetching partner stock list:", error)
    } finally {
        if (append || requestId === latestRequestId) {
            loadingFlag.value = false
        }
    }
}

const debouncedFetch = debounce(async (query: string) => {
    await fetchRows(getUrlFetch({ "filter[global]": query.trim() || undefined }))
}, 300)

const refreshSingleRow = async (rowData: any) => {
    const response = await axios.get(getUrlFetch({ "filter[global]": rowData.code }))
    overBudgetMessage.value = response.data.over_budget_message ?? null
    const updated = response.data.data.find((r: any) => r.id === rowData.id)
    if (updated) {
        const idx = rows.value.findIndex((r: any) => r.id === rowData.id)
        if (idx !== -1) {
            updated.quantity_ordered = updated.quantity_ordered ? Number(updated.quantity_ordered) : updated.quantity_ordered
            rows.value[idx] = updated
        }
    }
}

const onSubmitRow = async (row: any) => {
    isRowLoading.value = row.id

    try {
        const quantity = Number(row.quantity_ordered) || 0
        if (quantity > 0 && row.saveRoute) {
            const method = String(row.saveRoute.method ?? "post").toLowerCase()
            await axios[method](route(row.saveRoute.name, row.saveRoute.parameters), { quantity })
            await refreshSingleRow(row)
        } else if (quantity === 0 && row.deleteRoute) {
            await axios.delete(route(row.deleteRoute.name, row.deleteRoute.parameters))
            await refreshSingleRow(row)
        }
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Failed to add or update the quantity"),
            type: "error",
        })
    } finally {
        isRowLoading.value = null
    }
}

const debSubmitRow = debounce(onSubmitRow, 500)

const lineValue = (row: any): number | null => {
    const quantity = Number(row.quantity_ordered) || 0
    if (!quantity || row.price_per_sko === null || row.price_per_sko === undefined) return null

    return Math.round(quantity * Number(row.price_per_sko) * 100) / 100
}

const nextBatchMultiple = (row: any): number | null => {
    const quantum = Number(row.order_quantum) || 0
    if (quantum < 2) return null

    const quantity = Number(row.quantity_ordered) || 0
    const suggestion = Math.max(quantum, Math.ceil(quantity / quantum) * quantum)

    return suggestion === quantity ? null : suggestion
}

const leavesPartBatch = (row: any): boolean => {
    const quantum = Number(row.order_quantum) || 0
    const quantity = Number(row.quantity_ordered) || 0

    return quantum > 1 && quantity > 0 && quantity % quantum !== 0
}

const onUseBatchMultiple = (row: any) => {
    const suggestion = nextBatchMultiple(row)
    if (!suggestion) return

    row.quantity_ordered = suggestion
    onSubmitRow(row)
}

const onFetchNext = async (event: Event) => {
    const target = event.target as HTMLElement
    const nearBottom = target.scrollHeight - target.scrollTop - target.clientHeight < 150
    if (nearBottom && optionsLinks.value?.next && !isLoading.value && !isLoadingMore.value) {
        await fetchRows(optionsLinks.value.next, true)
    }
}

const debouncedFetchNext = debounce(onFetchNext, 200)

const getScrollContainer = (): Element | null => {
    return contentRef.value?.querySelector(".p-datatable-table-container")
        ?? contentRef.value?.querySelector(".p-datatable-scrollable-body")
        ?? null
}

const attachScrollListener = () => {
    const scrollContainer = getScrollContainer()
    if (scrollContainer) {
        scrollContainer.removeEventListener("scroll", debouncedFetchNext)
        scrollContainer.addEventListener("scroll", debouncedFetchNext)
    }
}

const detachScrollListener = () => {
    getScrollContainer()?.removeEventListener("scroll", debouncedFetchNext)
}

watch(searchQuery, (newValue) => {
    debouncedFetch(newValue)
})

onMounted(() => {
    fetchRows()
})

onUnmounted(() => {
    detachScrollListener()
})

watch(() => model.value, async (newValue) => {
    if (newValue === true) {
        await nextTick()
        attachScrollListener()
        await fetchRows(getUrlFetch({ "filter[global]": searchQuery.value.trim() || undefined }))
        await nextTick()
        attachScrollListener()
    }
})
</script>

<template>
    <Dialog
        v-model:visible="model"
        modal
        dismissableMask
        :header="trans('Partner stocks')"
        :style="{ width: '90vw', maxWidth: '1024px' }"
        :breakpoints="{ '768px': '95vw' }"
        @hide="detachScrollListener">
        <div ref="contentRef" class="flex flex-col justify-between h-[600px] overflow-y-auto pb-4">
            <div>
                <div v-if="overBudgetMessage" class="mb-3 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800">
                    <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="mt-0.5 text-red-600" fixed-width aria-hidden="true" />
                    <div>
                        <span class="font-semibold">{{ trans("Over the recommended budget.") }}</span>
                        {{ overBudgetMessage }}
                    </div>
                </div>

                <div class="card w-full">
                    <DataTable :value="rows" scrollable scrollHeight="400px">
                        <template #header>
                            <div class="flex items-center justify-end gap-3">
                                <span v-if="isLoading" class="flex items-center gap-2 text-sm text-gray-500">
                                    <LoadingIcon />
                                    {{ rows.length ? trans("Refreshing") : trans("Loading stocks") }}
                                </span>
                                <IconField>
                                    <InputIcon>
                                        <FontAwesomeIcon icon="fal fa-search" class="text-gray-500" fixed-width aria-hidden="true" />
                                    </InputIcon>
                                    <InputText
                                        v-model="searchQuery"
                                        :placeholder="trans('Search stocks')"
                                        class="border border-gray-300 rounded-lg px-4 py-2 text-sm" />
                                </IconField>
                            </div>
                        </template>

                        <template #empty>
                            <div v-if="isLoading" class="flex justify-center py-8 text-3xl text-gray-400">
                                <LoadingIcon />
                            </div>
                            <template v-else>{{ trans("No stocks found") }}.</template>
                        </template>

                        <Column header="Image">
                            <template #body="slotProps">
                                <div class="w-16 h-16 rounded">
                                    <Image :src="slotProps.data.image_sources" />
                                </div>
                            </template>
                        </Column>
                        <Column field="code" header="Code" />
                        <Column field="name" header="Name">
                            <template #body="slotProps">
                                <div>
                                    <div>{{ slotProps.data.name }}</div>
                                    <div class="opacity-60 text-sm italic" :class="Number(slotProps.data.available_quantity) > 0 ? '' : 'text-red-500'">
                                        {{ trans("Available at partner") }}: {{ slotProps.data.available_quantity ?? 0 }} {{ trans("SKO") }}
                                        <span v-if="slotProps.data.packed_in">({{ trans("packed in") }} {{ slotProps.data.packed_in }}s)</span>
                                    </div>
                                    <div class="text-xs text-teal-600">
                                        {{ trans("Your stock") }}: {{ slotProps.data.buyer_quantity_available ?? 0 }} {{ trans("SKO") }}
                                        <template v-if="slotProps.data.buyer_days_of_cover !== null && slotProps.data.buyer_days_of_cover !== undefined">
                                            &middot;
                                            <StockCoverLabel
                                                :days="slotProps.data.buyer_days_of_cover"
                                                v-tooltip="slotProps.data.buyer_out_of_stock_at
                                                    ? `${trans('At the current rate, empty on')} ${slotProps.data.buyer_out_of_stock_at}`
                                                    : undefined" />
                                        </template>
                                    </div>
                                    <div v-if="Number(slotProps.data.batch_size) > 1" class="text-xs text-gray-500">
                                        {{ trans("Made in batches of") }} <span class="font-medium">{{ slotProps.data.batch_size }}</span> {{ trans("units") }}
                                        <template v-if="Number(slotProps.data.order_quantum) > 1">
                                            &middot; {{ trans("full batches every") }} <span class="font-medium">{{ slotProps.data.order_quantum }}</span> {{ trans("SKO") }}
                                        </template>
                                        <button
                                            v-if="nextBatchMultiple(slotProps.data)"
                                            type="button"
                                            class="ml-1 rounded bg-indigo-50 px-1.5 py-px text-indigo-700 hover:bg-indigo-100"
                                            @click="onUseBatchMultiple(slotProps.data)">
                                            {{ trans("order") }} {{ nextBatchMultiple(slotProps.data) }}
                                        </button>
                                    </div>
                                    <div v-if="leavesPartBatch(slotProps.data)" class="text-xs text-amber-600">
                                        {{ trans("A full batch is made either way, so this order may be delayed or the quantity adjusted") }}
                                    </div>
                                    <div v-if="slotProps.data.buyer_quarterly_usage?.length" class="text-xs text-gray-500">
                                        {{ trans("Your usage") }}:
                                        <span v-for="record in slotProps.data.buyer_quarterly_usage" :key="record.period" class="mr-2">
                                            {{ record.period }}: <span class="font-medium">{{ record.sales }}</span>
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <Column :header="trans('SKOs')" style="width: 10%">
                            <template #body="slotProps">
                                <NumberWithButtonSave
                                    :key="slotProps.data.id"
                                    isWithRefreshModel
                                    v-model="slotProps.data.quantity_ordered"
                                    :min="0"
                                    :isLoading="isRowLoading === slotProps.data.id"
                                    @update:modelValue="() => debSubmitRow(slotProps.data)"
                                    noUndoButton
                                    noSaveButton
                                />
                                <div v-if="lineValue(slotProps.data) !== null" class="mt-1 text-right text-sm font-medium tabular-nums"
                                    :title="`${locale.currencyFormat(currency, slotProps.data.price_per_sko)} / ${trans('SKO')}`">
                                    {{ locale.currencyFormat(currency, lineValue(slotProps.data)) }}
                                </div>
                                <div v-else-if="slotProps.data.price_per_sko" class="mt-1 text-right text-xs text-gray-400 tabular-nums">
                                    {{ locale.currencyFormat(currency, slotProps.data.price_per_sko) }} / {{ trans("SKO") }}
                                </div>
                            </template>
                        </Column>

                        <template #footer>
                            <div class="flex items-center justify-center gap-2">
                                {{ trans("Showing") }} {{ rows.length }} {{ trans("stocks") }}.
                                <span v-if="isLoadingMore" class="flex items-center gap-1 text-sm text-gray-500">
                                    <LoadingIcon />
                                    {{ trans("Loading more") }}
                                </span>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
    </Dialog>
</template>
