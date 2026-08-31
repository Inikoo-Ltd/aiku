<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
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
import { faMinus, faPlus } from "@fas"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import Image from "@common/Components/Image.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faSearch, faPlus, faMinus, faSpinner)

const props = defineProps<{
    fetchRoute: routeType
}>()

const model = defineModel()
const rows = ref<any[]>([])
const optionsLinks = ref<any>(null)
const isLoading = ref(false)
const isRowLoading = ref<number | null>(null)
const searchQuery = ref("")

const closeModal = () => {
    model.value = false
}

const getUrlFetch = (additionalParams: {}) => {
    return route(props.fetchRoute.name, {
        ...props.fetchRoute.parameters,
        ...additionalParams,
    })
}

const fetchRows = async (url?: string, append = false) => {
    isLoading.value = true
    const urlToFetch = url || getUrlFetch({})

    try {
        const response = await axios.get(urlToFetch)
        rows.value = append ? [...rows.value, ...response.data.data] : response.data.data
        optionsLinks.value = { next: response.data.links?.next ?? response.data.next_page_url }
    } catch (error) {
        console.error("Error fetching partner stock list:", error)
    } finally {
        isLoading.value = false
    }
}

const debouncedFetch = debounce(async (query: string) => {
    await fetchRows(getUrlFetch({ "filter[global]": query.trim() || undefined }))
}, 300)

const refreshSingleRow = async (rowData: any) => {
    const response = await axios.get(getUrlFetch({ "filter[global]": rowData.code }))
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

const onFetchNext = async (event: Event) => {
    const target = event.target as HTMLElement
    const nearBottom = target.scrollHeight - target.scrollTop - target.clientHeight < 150
    if (nearBottom && optionsLinks.value?.next && !isLoading.value) {
        await fetchRows(optionsLinks.value.next, true)
    }
}

const debouncedFetchNext = debounce(onFetchNext, 200)

const attachScrollListener = () => {
    const tableBody = document.querySelector(".p-datatable-tbody")?.closest(".p-datatable-table-container")
        ?? document.querySelector(".p-datatable-scrollable-body")
    if (tableBody) {
        tableBody.removeEventListener("scroll", debouncedFetchNext)
        tableBody.addEventListener("scroll", debouncedFetchNext)
    }
}

watch(searchQuery, (newValue) => {
    debouncedFetch(newValue)
})

onMounted(() => {
    fetchRows()
})

onUnmounted(() => {
    const tableBody = document.querySelector(".p-datatable-tbody")?.closest(".p-datatable-table-container")
        ?? document.querySelector(".p-datatable-scrollable-body")
    if (tableBody) {
        tableBody.removeEventListener("scroll", debouncedFetchNext)
    }
})

watch(() => model.value, async (newValue) => {
    if (newValue === true) {
        await fetchRows(getUrlFetch({ "filter[global]": searchQuery.value.trim() || undefined }))
        await nextTick()
        attachScrollListener()
    }
})
</script>

<template>
    <KeepAlive>
        <Modal :isOpen="model" @onClose="closeModal" :closeButton="true" width="w-full max-w-2xl md:max-w-5xl">
            <div class="flex flex-col justify-between h-[600px] overflow-y-auto pb-4 px-3">
                <div>
                    <div class="flex justify-center py-2 text-gray-600 font-medium mb-3">
                        <h2>{{ trans("Partner stocks") }}</h2>
                    </div>

                    <div class="card w-full">
                        <DataTable :value="rows" scrollable scrollHeight="400px" :loading="isLoading">
                            <template #header>
                                <div class="flex justify-end">
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

                            <template #empty>{{ trans("No stocks found") }}.</template>

                            <template #loading>
                                <div class="text-5xl">
                                    <LoadingIcon />
                                </div>
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
                                </template>
                            </Column>

                            <template #footer>
                                <div class="text-center">
                                    {{ trans("Showing") }} {{ rows.length }} {{ trans("stocks") }}.
                                </div>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </div>
        </Modal>
    </KeepAlive>
</template>
