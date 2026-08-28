<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 04 May 2025 17:37:38 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TablePortfolios from "@/Components/Tables/Grp/Org/CRM/TablePortfolios.vue"
import TablePortfoliosShopify from "@/Components/Tables/Grp/Org/CRM/TablePortfoliosShopify.vue"
import TablePortfoliosManual from "@/Components/Tables/Grp/Org/CRM/TablePortfoliosManual.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { ref, computed, onMounted } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ProductsSelector from "@/Components/Dropshipping/ProductsSelector.vue"
import { notify } from "@kyvg/vue3-notification"
import { Customer } from "@/types/customer"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBookmark, faDownload, faEllipsisV } from "@fal"
import { Popover } from "primevue"
import { routeType } from "@/types/route"
import { ulid } from "ulid"
import {debounce} from "lodash-es"
import axios from "axios"
import { trans } from "laravel-vue-i18n"

library.add(faBookmark, faDownload, faEllipsisV)

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
    customer: Customer
    is_show_add_products_modal: boolean
    customerSalesChannelId: number
    platform: {}
    customerSalesChannel: {}
    routes:{}
    download_route?: Record<string, routeType>
}>()


const isOpenModalPortfolios = ref(false)


// Method: Submit the selected item
const isLoadingSubmit = ref(false)
const onSubmitAddItem = async (idProduct: number[], customerSalesChannelId: number) => {
    router.post(route("grp.models.customer_sales_channel.portfolio.store_multiple_manual", { customerSalesChannel: customerSalesChannelId }), {
        items: idProduct
    }, {
        onBefore: () => isLoadingSubmit.value = true,
        onError: (error) => {
            notify({
                title: "Something went wrong.",
                text: error.products || undefined,
                type: "error"
            })
        },
        onSuccess: () => {
            router.reload({ only: ["data"] })
            notify({
                title: trans("Success!"),
                text: trans("Successfully added the portfolio"),
                type: "success"
            })
            isOpenModalPortfolios.value = false
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}
const loadingAction= ref([])
const selectedProducts = ref<number[]>([])
const key = ref(ulid())

const progessbar = ref({
    data: {
        number_success: 0,
        number_fails: 0
    },
    done: true,
    total: selectedProducts.value.length,
})


const onSuccessEditCheckmark = () => {
  progessbar.value = {...progessbar.value , done : false, total : selectedProducts.value.length}
}

const onFailedEditCheckmark = (error: any) => {
    notify({
        title: "Something went wrong.",
        text: error?.products || "An error occurred.",
        type: "error",
    })
}


const _export_popover = ref()

const downloadUrl = (type: string, extraParams: Record<string, unknown> = {}) => {
    if (props.download_route?.[type]?.name) {
        return route(props.download_route[type].name, {
            ...props.download_route[type].parameters,
            ...extraParams,
        })
    }

    return ""
}

const productAvailibility = [
    { key: "exclude_not_for_sale", label: trans("Exclude products that are not for sale") },
    { key: "exclude_out_of_stocks", label: trans("Exclude products that are out of stock") },
    { key: "only_not_for_sale", label: trans("Only products that are not for sale") },
]

const productStates = [
    { key: "active", label: "Active" },
    { key: "discontinuing", label: "Discontinuing" },
    { key: "discontinued", label: "Discontinued" },
]

const extendedColumns = [
    { key: "product_code", label: "Product code" },
    { key: "product_user_reference", label: "Product user reference" },
    { key: "department_code", label: "Department code" },
    { key: "department_name", label: "Department name" },
    { key: "subdepartment_code", label: "Sub Department code" },
    { key: "subdepartment_name", label: "Sub Department name" },
    { key: "family_code", label: "Family code" },
    { key: "family_name", label: "Family name" },
    { key: "product_name", label: "Product name" },
    { key: "materials_ingredients", label: "Materials/Ingredients" },
    { key: "unit_dimensions", label: "Unit dimensions" },
    { key: "unit_net_weight", label: "Unit net weight (kg)" },
    { key: "package_weight_shipping", label: "Package weight (shipping)" },
    { key: "country_of_origin", label: "Country of origin" },
    { key: "tariff_code", label: "Tariff code" },
    { key: "duty_rate", label: "Duty rate" },
    { key: "hts_us", label: "HTS US" },
    { key: "available_quantity", label: "Stock" },
    { key: "status", label: "Status" },
    { key: "for_sale", label: "For sale" },
    { key: "data_updated", label: "Data updated" },
]

const isDownloadingExtendedProperties = ref(false)
const selectedExtendedColumns = ref<string[]>(extendedColumns.map((c) => c.key))
const selectedProductStates = ref<string[]>(["active"])
const selectedProductAvailibility = ref<string[]>([])
const includeBundles = ref(false)

const csvDownloadUrl = computed(
    () => downloadUrl("csv", { include_bundles: includeBundles.value ? 1 : 0 }) as string
)

const allSelected = computed(
    () => selectedExtendedColumns.value.length === extendedColumns.length
)

const toggleSelectAll = () => {
    selectedExtendedColumns.value = allSelected.value ? [] : extendedColumns.map((c) => c.key)
}

const onDownloadExtendedProperties = () => {
    if (!selectedExtendedColumns.value.length) {
        notify({
            title: trans("Select at least one column"),
            type: "warn",
        })
        return
    }

    const url = downloadUrl("extended_properties", {
        columns: selectedExtendedColumns.value,
        product_states: selectedProductStates.value,
        product_availability: selectedProductAvailibility.value,
        include_bundles: includeBundles.value ? 1 : 0,
    })

    if (!url) {
        notify({
            title: trans("No route defined"),
            type: "error",
        })
        return
    }

    isDownloadingExtendedProperties.value = true
    window.open(typeof url === "string" ? url : url.toString(), "_blank", "noopener")
    setTimeout(() => {
        isDownloadingExtendedProperties.value = false
    }, 400)
}

const submitPortfolioAction = async (action: any) => {
  try {
    loadingAction.value.push(action.label)

    const method = action.route?.method?.toLowerCase() || 'get'
    const url = route(action.route.name, action.route?.parameters)

    const payload = { portfolios: selectedProducts.value }

    let response

    if (method === 'get') {
      response = await axios.get(url, { params: payload })
    } else {
      response = await axios[method](url, payload)
    }

    onSuccessEditCheckmark(response.data)
  } catch (error: any) {
    onFailedEditCheckmark(error)
  } finally {
    loadingAction.value = []
  }
}



</script>

<template>
    <Head :title="capitalize(title)" ></Head>
    <PageHeading :data="pageHead">
        <template #other>
            <div class="flex items-center gap-x-3">
                <div v-if="download_route"
                    class="inline-flex items-center rounded-md border overflow-hidden">
                    <a :href="csvDownloadUrl" target="_blank" rel="noopener">
                        <Button :icon="faDownload" label="CSV" type="tertiary"
                            class="h-9 px-3 py-0 border-0 rounded-none border-r" />
                    </a>
                    <Button @click="(e: MouseEvent) => _export_popover?.toggle(e)"
                        v-tooltip="trans('Other Export Options')" :icon="faEllipsisV"
                        class="h-9 px-2 py-0 border-0 rounded-none" type="tertiary" />
                    <Popover ref="_export_popover">
                        <div class="w-72 flex flex-col max-h-[75vh] bg-white rounded-md shadow-lg">
                            <div
                                class="px-4 py-3 border-b bg-gray-50 flex justify-between items-center sticky top-0 z-10">
                                <span class="font-semibold text-sm text-gray-700">
                                    {{ trans("Export Options") }}
                                </span>
                                <button @click="toggleSelectAll"
                                    class="text-xs text-blue-600 hover:underline font-medium">
                                    {{ allSelected ? trans("Deselect All") : trans("Select All") }}
                                </button>
                            </div>

                            <div class="p-4 overflow-y-auto space-y-5 text-sm">
                                <div>
                                    <div class="font-medium text-gray-800 mb-2">
                                        {{ trans("Bundles") }}
                                    </div>
                                    <label
                                        class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-1 rounded">
                                        <input type="checkbox" v-model="includeBundles"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        <span>{{ trans("Include bundles") }}</span>
                                    </label>
                                    <div class="mt-1 pl-1 text-xs text-gray-500">
                                        {{ trans("Applies to both CSV exports. Off by default.") }}
                                    </div>
                                </div>

                                <div class="border-t pt-4">
                                    <div class="font-medium text-gray-800 mb-2">
                                        {{ trans("Columns to Export") }}
                                    </div>
                                    <div class="space-y-2">
                                        <label v-for="col in extendedColumns" :key="col.key"
                                            class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-1 rounded">
                                            <input type="checkbox" :value="col.key"
                                                v-model="selectedExtendedColumns"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                            <span
                                                :class="{ 'opacity-60': !selectedExtendedColumns.includes(col.key) }">
                                                {{ trans(col.label) }}
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="border-t pt-4">
                                    <div class="font-medium text-gray-800 mb-2">
                                        {{ trans("Product State") }}
                                    </div>
                                    <div class="space-y-2">
                                        <label v-for="state in productStates" :key="state.key"
                                            class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-1 rounded">
                                            <input type="checkbox" :value="state.key"
                                                v-model="selectedProductStates"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                            <span>{{ trans(state.label) }}</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="border-t pt-4">
                                    <div class="font-medium text-gray-800 mb-2">
                                        {{ trans("Product Sale Status") }}
                                    </div>
                                    <div class="space-y-2">
                                        <label v-for="availibility in productAvailibility"
                                            :key="availibility.key"
                                            class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-1 rounded">
                                            <input type="checkbox" :value="availibility.key"
                                                v-model="selectedProductAvailibility"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                            <span>{{ trans(availibility.label) }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 border-t bg-white sticky bottom-0 z-10">
                                <Button :loading="isDownloadingExtendedProperties"
                                    :disabled="isDownloadingExtendedProperties" type="primary"
                                    class="w-full !px-3 !py-2 !justify-center"
                                    @click="onDownloadExtendedProperties"
                                    :label="trans('Export Extended Properties')" />
                            </div>
                        </div>
                    </Popover>
                </div>

                <Button v-if="is_show_add_products_modal" @click="() => isOpenModalPortfolios = true"
                    :type="'secondary'" icon="fal fa-plus"
                    :label="trans('Add products to portfolio')" />
            </div>
        </template>

        <template #button-match-with-existing-product="{ action }">
            <Button v-if="selectedProducts.length > 0" :type="action.style" :label="action.label"
                 :loading="loadingAction.includes(action.label)"
                @click="() => submitPortfolioAction(action)" />
            <div v-else></div>
        </template>

        <template #button-create-new-product="{ action }">
            <Button v-if="selectedProducts.length > 0" :type="action.style" :label="action.label"
                :loading="loadingAction.includes(action.label)"
                @click="() => submitPortfolioAction(action)" />
            <div v-else></div>
        </template>
    </PageHeading>

    <TablePortfoliosShopify v-if="platform.type === 'shopify'" :data="data" :customerSalesChannel v-model:selectedProducts="selectedProducts" :key="key" :progressToUploadToShopifyAll="progessbar"/>
    <TablePortfoliosManual v-else-if="platform.type === 'manual'" :data="data" :customerSalesChannel />
    <TablePortfolios v-else :data="data" :customerSalesChannel  v-model:selectedProducts="selectedProducts" :key="key"    :progressToUploadToShopifyAll="progessbar"  :routes="props.routes"/>


    <Modal v-if="is_show_add_products_modal" :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false"
        width="w-full max-w-6xl">
        <ProductsSelector :headLabel="trans('Add products to portfolios')" :route-fetch="{
            name: 'grp.json.products_for_portfolio_select',
            parameters: {
                customerSalesChannel: customerSalesChannelId
            }
        }" :isLoadingSubmit
            @submit="(products: {}[]) => onSubmitAddItem(products.map((product: any) => product.id), customerSalesChannelId)">
        </ProductsSelector>
    </Modal>
</template>