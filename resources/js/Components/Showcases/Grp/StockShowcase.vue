<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref, computed, onMounted } from "vue"
import { Link } from "@inertiajs/vue3"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrash as falTrash, faShoppingBasket, faEdit, faExternalLink, faStickyNote, faStopCircle, faFilePdf, faWeightHanging, faRulerCombined } from "@fal"
import { faCircle, faPlay, faTrash, faPlus } from "@fas"
import { faExclamationTriangle } from "@fad"
import StocksManagement from "@/Components/Warehouse/Inventory/StocksManagement/StocksManagement.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { StocksManagementTS } from "@/types/Inventory/StocksManagement"
import Image from "@common/Components/Image.vue"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import SalesAnalyticsCompact from "@/Components/Product/SalesAnalyticsCompact.vue"
import Icon from "@/Components/Icon.vue"
import JsBarcode from "jsbarcode"
import { ctrans } from "@/Composables/useTrans"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
library.add(faExclamationTriangle, faCircle, faTrash, falTrash, faShoppingBasket, faEdit, faExternalLink, faStickyNote, faPlay, faPlus, faStopCircle, faFilePdf, faWeightHanging, faRulerCombined)

const props = defineProps < {
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
            label: string
            number: string
            quantity: number
            weight: number
            dimensions: {
                h?: number
                l?: number
                w?: number
                type?: string
                units?: string
            }
            packs: number | null
        }[]
        label_route?: {
            name: string
            parameters: Record<string, string>
        }
        latest_movements?: {
            id: number
            date: string
            type_label: string
            class_icon: string | object
            quantity: string | number
            is_negative: boolean
            running_quantity_org_stock: string | number
            location_code: string | null
            user_name: string | null
            reason_label: string | null
        }[]
        stock_history_route?: routeType
    }
    reasons?: {
        increase: [],
        decrease: [],
        transfer: [],
    }
    org_stock_id: number
}>()

const layout = inject('layout', layoutStructure)
const locale = inject("locale", aikuLocaleStructure)
const selectedImage = ref(0)
const showAllStats = ref(false)

const displayedStats = computed(() => {
    if (!props.data.stats) return []
    const filtered = props.data.stats.filter(item => !item.name.toLowerCase().includes("all"))
    return showAllStats.value ? filtered : filtered.slice(0, 6)
})

const formatWeight = (weight: number | null | undefined): string | null => {
    if (weight == null) return null
    if (weight >= 1000) {
        return `${(weight / 1000).toLocaleString(locale.language.code, { maximumFractionDigits: 2 })} kg`
    }
    return `${weight.toLocaleString(locale.language.code, { maximumFractionDigits: 2 })} g`
}

const formatDimensions = (dimensions: { h?: number; l?: number; w?: number; units?: string } | null | undefined): string | null => {
    if (!dimensions) return null
    const sides = [dimensions.l, dimensions.w, dimensions.h].filter((value) => value != null)
    if (!sides.length) return null
    const units = dimensions.units ? ` ${dimensions.units}` : ""
    return `${sides.join(" × ")}${units}`
}

const stockCostStats = computed(() => {
    const stockCost = props.data.stocks_management?.stock_cost
    return [
        { title: ctrans("Future delivered cost"), value: stockCost?.current_supplier_sku_cost || 0 },
        { title: ctrans("SKO value"), value: stockCost?.sku_value || 0 },
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
        JsBarcode("#barcode-" + barcode.level, barcode.number, {
            format: barcode.level === "unit" ? "EAN13" : "CODE128",
            lineColor: "#000",
            width: 2,
            height: 50,
            displayValue: true,
        })
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
        </div>

        <!-- Section: Stocks Management -->
        <div class="md:col-span-2">
            <StocksManagement
                v-if="data.stocks_management"
                :data
                :stocks_management="data.stocks_management"
                :trade_units="data.trade_units"
                :reasons
                :org_stock_id
            />

            <!-- Barcodes -->
            <div v-if="data.barcodes?.length" class="mt-6 flex flex-col gap-3">
                <div v-for="barcode in data.barcodes" :key="barcode.level"
                    class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                    <div class="w-8 shrink-0 text-center text-lg text-gray-500">
                        <Icon :data="{ icon: 'fal fa-stop-circle' }" v-tooltip="trans(barcode.label)" />
                    </div>

                    <svg :id="'barcode-' + barcode.level" class="h-14"></svg>

                    <div class="flex flex-col gap-1.5 text-sm">
                        <span v-if="formatWeight(barcode.weight)"
                            v-tooltip="ctrans('Weight')"
                            class="inline-flex items-center gap-2 text-gray-700">
                            <Icon :data="{ icon: 'fal fa-weight-hanging' }" class="w-4 shrink-0 text-gray-400" />
                            <span class="font-medium tabular-nums">{{ formatWeight(barcode.weight) }}</span>
                        </span>

                        <span v-if="formatDimensions(barcode.dimensions)"
                            v-tooltip="ctrans('Dimensions (L × W × H)')"
                            class="inline-flex items-center gap-2 text-gray-700">
                            <Icon :data="{ icon: 'fal fa-ruler-combined' }" class="w-4 shrink-0 text-gray-400" />
                            <span class="font-medium tabular-nums">{{ formatDimensions(barcode.dimensions) }}</span>
                            <span v-if="barcode.dimensions?.type"
                                class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-500">
                                {{ barcode.dimensions.type }}
                            </span>
                        </span>
                    </div>

                    <a v-if="data.label_route"
                        :href="route(data.label_route.name, { ...data.label_route.parameters, level: barcode.level })"
                        target="_blank"
                        v-tooltip="ctrans('Open PDF label')"
                        class="ml-auto text-3xl text-gray-400 transition hover:text-red-500">
                        <Icon :data="{ icon: 'fal fa-file-pdf' }" />
                    </a>
                </div>
            </div>

            <!-- Latest movements -->
            <div v-if="data.latest_movements?.length" class="mt-6 rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        {{ trans("Latest movements") }}
                    </span>
                    <Link v-if="data.stock_history_route"
                        :href="route(data.stock_history_route.name, data.stock_history_route.parameters)"
                        class="text-xs font-medium text-indigo-500 hover:text-indigo-700">
                        {{ trans("View all") }}
                    </Link>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="movement in data.latest_movements" :key="movement.id"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm">
                        <Icon :data="movement.class_icon" class="w-4 shrink-0 text-gray-400" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium text-gray-700">
                                {{ movement.type_label }}
                                <span v-if="movement.reason_label" class="font-normal text-gray-500">· {{ movement.reason_label }}</span>
                            </div>
                            <div class="truncate text-xs text-gray-400">
                                {{ useFormatTime(movement.date, { formatTime: "hms" }) }}
                                <span v-if="movement.location_code"> · {{ movement.location_code }}</span>
                                <span v-if="movement.user_name"> · {{ movement.user_name }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold tabular-nums" :class="movement.is_negative ? 'text-red-500' : 'text-green-600'">
                                {{ movement.is_negative ? '' : '+' }}{{ movement.quantity }}
                            </div>
                            <div class="text-xs tabular-nums text-gray-400" v-tooltip="trans('Running quantity')">
                                {{ movement.running_quantity_org_stock }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
