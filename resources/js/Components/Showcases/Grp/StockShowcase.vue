<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref, computed, onMounted } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrash as falTrash, faShoppingBasket, faEdit, faExternalLink, faStickyNote, faStopCircle, faBox, faPallet, faTimes } from "@fal"
import { faCircle, faPlay, faTrash, faPlus } from "@fas"
import { faExclamationTriangle } from "@fad"
import StocksManagement from "@/Components/Warehouse/Inventory/StocksManagement/StocksManagement.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { StocksManagementTS } from "@/types/Inventory/StocksManagement"
import Image from "@common/Components/Image.vue"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import SalesAnalyticsCompact from "@/Components/Product/SalesAnalyticsCompact.vue"
import Icon from "@/Components/Icon.vue"
import { generateBarcode } from "@/Composables/printBarcode"
import { ctrans } from "@/Composables/useTrans"
library.add(faExclamationTriangle, faCircle, faTrash, falTrash, faShoppingBasket, faEdit, faExternalLink, faStickyNote, faPlay, faPlus, faStopCircle, faBox, faPallet, faTimes)

const props = defineProps<{
    data: {
        trade_units: {
            brand: {}
            brand_routes: {
                index_brand: routeType
                store_brand: routeType
                update_brand: routeType
                delete_brand: routeType
                attach_brand: routeType
                detach_brand: routeType
            }
            tag_routes: {
                index_tag: routeType
                store_tag: routeType
                update_tag: routeType
                delete_tag: routeType
                attach_tag: routeType
                detach_tag: routeType
            }
            tags: {}[]
            tags_selected_id: number[]
        }[]
        stocks_management: StocksManagementTS
        currency_code: string
        is_quantity_excess: boolean
        barcodes?: {
            level: string
            number: string
            quantity: number
            packs: number | null
        }[]
    }
}>()

const barcodeLevelIcon: Record<string, string> = {
    unit: "fal fa-stop-circle",
    outer: "fal fa-box",
    carton: "fal fa-pallet",
}

const layout = inject('layout', layoutStructure)
const locale = inject("locale", aikuLocaleStructure)
const selectedImage = ref(0)
const showAllStats = ref(false)

const displayedStats = computed(() => {
    if (!props.data.stats) return []
    const filtered = props.data.stats.filter(item => !item.name.toLowerCase().includes("all"))
    return showAllStats.value ? filtered : filtered.slice(0, 6)
})

const stockCostStats = computed(() => {
    const stockCost = props.data.stocks_management?.stock_cost
    return [
        { title: ctrans("Future delivered cost"), value: stockCost?.current_supplier_sku_cost || 0 },
        { title: ctrans("SKU value"), value: stockCost?.sku_value || 0 },
        { title: ctrans("Total stock value"), value: stockCost?.total_stock_value || 0 },
    ]
})

// watch(images, (newVal) => {
//     if (!newVal?.length || selectedImage.value > newVal.length - 1) {
//         selectedImage.value = 0
//     }
// }, { immediate: true })


onMounted(() => {
    props.data.barcodes?.forEach((barcode) => {
        generateBarcode("barcode-" + barcode.level, barcode.number)
    })
})

</script>


<template>
    <div class="grid md:grid-cols-4 gap-6 p-6">
        <!-- Section: Trade Units -->
        <div class="md:col-span-2">
            <!-- Header: Unit label + Product name -->
            <div class="flex items-center gap-2 border-b pb-3 mb-4">
                <ProductUnitLabel v-if="data?.trade_units?.[0]?.units"
                    :units="data.trade_units[0].units"
                    :unit="data.trade_units[0].unit" />
                <span class="align-middle text-xl font-semibold text-gray-800">
                    {{ data?.trade_units?.[0]?.name }}
                </span>
            </div>

            <!-- Body: Image + Stock Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Image -->
                <div class="sm:col-span-1">
                    <Image v-if="data?.trade_units?.[0]?.images?.[0]?.images"
                        :src="data.trade_units[0].images[0].images"
                        class="w-full h-52 flex items-center justify-center" />
                </div>

                <!-- Card: Stock Summary + Sales Analytics -->
                <div class="sm:col-span-2 flex flex-col gap-4 self-start">
                    <div class="flex flex-wrap gap-3">
                        <div v-for="stat in stockCostStats" :key="stat.title"
                            class="flex-1 min-w-max rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-400 whitespace-nowrap">
                                {{ stat.title }}
                            </div>
                            <div class="mt-1.5 text-2xl font-bold text-gray-800 whitespace-nowrap">
                                {{ locale.currencyFormat(data.currency_code, stat.value) }}
                            </div>
                        </div>
                    </div>

                    <SalesAnalyticsCompact v-if="data.sales_data" :salesData="data.sales_data" />
                </div>
            </div>

            <!-- Barcodes -->
            <div v-if="data.barcodes?.length" class="mt-6 border-t pt-4">
                <div class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">
                    {{ ctrans("Barcodes") }}
                </div>
                <div class="flex flex-col gap-3">
                    <div v-for="barcode in data.barcodes" :key="barcode.level"
                        class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                        <div class="w-8 shrink-0 text-center text-lg text-gray-500">
                            <Icon :data="{ icon: barcodeLevelIcon[barcode.level] }" />
                        </div>

                        <div class="w-24 shrink-0 text-sm text-gray-600">
                            <span v-if="barcode.level === 'outer'"
                                v-tooltip="ctrans('Units per pack')"
                                class="inline-flex items-center gap-0.5">
                                <Icon :data="{ icon: 'fal fa-stop-circle', class: 'text-gray-300' }" />
                                <Icon :data="{ icon: 'fal fa-times', class: 'text-gray-300' }" />
                                <span>{{ barcode.quantity }}</span>
                            </span>
                            <span v-else-if="barcode.level === 'carton'"
                                class="inline-flex items-center gap-0.5">
                                <span v-tooltip="ctrans('Units per carton')" class="inline-flex items-center gap-0.5">
                                    <Icon :data="{ icon: 'fal fa-stop-circle', class: 'text-gray-300' }" />
                                    <Icon :data="{ icon: 'fal fa-times', class: 'text-gray-300' }" />
                                    <span>{{ barcode.quantity }}</span>
                                </span>
                                <span v-if="barcode.packs"
                                    v-tooltip="ctrans('Packages (SKOs) per carton')"
                                    class="text-gray-400">
                                    ({{ barcode.packs }})
                                </span>
                            </span>
                        </div>

                        <svg :id="'barcode-' + barcode.level" class="h-14"></svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Stocks Management -->
        <div class="md:col-span-2">
            <StocksManagement
                v-if="data.stocks_management"
                :data
                :stocks_management="data.stocks_management"
                :trade_units="data.trade_units"
            />
        </div>
    </div>
</template>
