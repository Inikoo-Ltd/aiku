<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref, computed, onMounted, nextTick } from "vue"
import { Link, router } from "@inertiajs/vue3"
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
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import JsBarcode from "jsbarcode"
import { ctrans } from "@/Composables/useTrans"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useConfirm } from "primevue/useconfirm"
import ConfirmDialog from "primevue/confirmdialog"
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
            editable: boolean
        }[]
        barcode_update_route?: routeType
        can_edit_unit_barcode?: boolean
        unit_barcode_update_route?: routeType
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
            quantity_fractional?: [number, [number, number]]
            is_negative: boolean
            running_quantity_org_stock: string | number
            running_quantity_org_stock_fractional?: [number, [number, number]]
            is_running_negative?: boolean
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


const renderBarcodes = () => {
    props.data.barcodes?.forEach((barcode) => {
        if (!barcode.number) return
        JsBarcode("#barcode-" + barcode.level, barcode.number, {
            format: /^\d{13}$/.test(barcode.number) ? "EAN13" : "CODE128",
            lineColor: "#000",
            width: 2,
            height: 50,
            displayValue: true,
        })
    })
}

onMounted(renderBarcodes)

// Section: edit or add a barcode (SKO or unit EAN), typed by hand or fed by a scanner into the input
const isBarcodeModalOpen = ref(false)
const barcodeInput = ref("")
const isSavingBarcode = ref(false)
const editingLevel = ref<string>("sko")
const editingHasNumber = ref(false)

const barcodeInputElement = ref<HTMLInputElement | null>(null)

const barcodeRouteForLevel = (level: string): routeType | undefined =>
    level === "unit" ? props.data.unit_barcode_update_route : props.data.barcode_update_route

const canEditBarcode = (barcode: { level: string, editable: boolean }): boolean => {
    if (barcode.level === "unit") return !!barcode.editable && !!props.data.can_edit_unit_barcode
    return !!barcode.editable && !!props.data.barcode_update_route
}

const openBarcodeModal = (barcode: { level: string, number: string }) => {
    editingLevel.value = barcode.level
    editingHasNumber.value = !!barcode.number
    barcodeInput.value = barcode.number || ""
    isBarcodeModalOpen.value = true
    nextTick(() => barcodeInputElement.value?.focus())
}

const applyBarcodeChange = (value: string | null) => {
    const route_ = barcodeRouteForLevel(editingLevel.value)
    if (!route_ || isSavingBarcode.value) return

    const field = editingLevel.value === "unit" ? "unit_barcode" : "barcode"

    router.patch(
        route(route_.name, route_.parameters),
        { [field]: value },
        {
            preserveScroll: true,
            onStart: () => isSavingBarcode.value = true,
            onFinish: () => isSavingBarcode.value = false,
            onSuccess: () => {
                isBarcodeModalOpen.value = false
                nextTick(renderBarcodes)
            },
            onError: (errors) => {
                notify({
                    title: trans("Something went wrong"),
                    text: errors[field] || errors.unit_barcode || trans("Could not save the barcode"),
                    type: "error",
                })
            },
        }
    )
}

const confirm = useConfirm()

const saveBarcode = (value: string | null) => {
    if (editingLevel.value !== "unit") {
        applyBarcodeChange(value)
        return
    }

    confirm.require({
        message: trans("Changing the unit EAN updates the product barcode on the website and every sales channel (Shopify, eBay, Amazon, etc.) — this affects customers. Continue?"),
        header: trans("Confirm unit EAN change"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("Yes, update it"),
        rejectLabel: trans("Cancel"),
        rejectProps: {
            label: trans("Cancel"),
            severity: "secondary",
            outlined: true
        },
        accept: () => applyBarcodeChange(value),
    })
}

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
            <div v-if="data.barcodes?.length"
                class="mt-6 grid gap-y-3 gap-x-4 items-center"
                style="grid-template-columns: auto auto auto auto 1fr;">
                <template v-for="barcode in data.barcodes" :key="barcode.level">
                    <div class="contents">
                        <div class="w-12 shrink-0 text-sm font-medium uppercase tracking-wide text-gray-500"
                            v-tooltip="trans(barcode.label)">
                            {{ barcode.level === 'sko' ? ctrans('SKO') : ctrans('Unit') }}
                        </div>

                        <a v-if="barcode.number && data.label_route"
                            :href="route(data.label_route.name, { ...data.label_route.parameters, level: barcode.level })"
                            target="_blank"
                            v-tooltip="ctrans('Open PDF label')"
                            class="justify-self-start transition hover:opacity-60">
                            <svg :id="'barcode-' + barcode.level" class="h-14"></svg>
                        </a>
                        <svg v-else-if="barcode.number" :id="'barcode-' + barcode.level" class="h-14 justify-self-start"></svg>

                        <button
                            v-else-if="canEditBarcode(barcode)"
                            type="button"
                            class="flex h-14 px-2 items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 text-gray-400 transition hover:border-indigo-400 hover:text-indigo-500"
                            @click="openBarcodeModal(barcode)">
                            <Icon :data="{ icon: 'fal fa-plus' }" />
                            {{ ctrans("Add barcode (type or scan it)") }}
                        </button>

                        <div v-else class="h-14 text-sm italic text-gray-400 flex items-center">{{ ctrans("No barcode") }}</div>

                        <button
                            v-if="barcode.number && canEditBarcode(barcode)"
                            type="button"
                            v-tooltip="ctrans('Edit barcode')"
                            class="text-gray-300 transition hover:text-indigo-500"
                            @click="openBarcodeModal(barcode)">
                            <Icon :data="{ icon: 'fal fa-edit' }" />
                        </button>
                        <span v-else></span>

                        <span v-if="formatWeight(barcode.weight)"
                            v-tooltip="barcode.level === 'sko' ? ctrans('Outer packing weight') : ctrans('Marketing weight (shown on website)')"
                            class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <Icon :data="{ icon: 'fal fa-weight-hanging' }" class="w-4 shrink-0 text-gray-400" />
                            <span class="font-medium tabular-nums">{{ formatWeight(barcode.weight) }}</span>
                        </span>
                        <span v-else class="text-sm text-gray-300">—</span>

                        <span v-if="formatDimensions(barcode.dimensions)"
                            v-tooltip="ctrans('Dimensions (L × W × H)')"
                            class="inline-flex items-center gap-2 text-sm text-gray-700 whitespace-nowrap">
                            <Icon :data="{ icon: 'fal fa-ruler-combined' }" class="w-4 shrink-0 text-gray-400" />
                            <span class="font-medium tabular-nums">{{ formatDimensions(barcode.dimensions) }}</span>
                            <span v-if="barcode.dimensions?.type"
                                class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-500">
                                {{ barcode.dimensions.type }}
                            </span>
                        </span>
                        <span v-else class="text-sm text-gray-300">—</span>

                    </div>
                </template>
            </div>

            <!-- Latest movements -->
            <div v-if="data.latest_movements?.length" class="mt-6 rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        {{ ctrans("Latest movements") }}
                    </span>
                    <Link v-if="data.stock_history_route"
                        :href="route(data.stock_history_route.name, data.stock_history_route.parameters)"
                        class="text-xs font-medium text-indigo-500 hover:text-indigo-700">
                        {{ ctrans("View all") }}
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
                            <div class="font-semibold tabular-nums flex items-center justify-end" :class="movement.is_negative ? 'text-red-500' : 'text-green-600'">
                                <template v-if="movement.quantity_fractional">
                                    {{ movement.is_negative ? '−' : '+' }}
                                    <FractionDisplay :fractionData="movement.quantity_fractional" />
                                </template>
                                <template v-else>
                                    {{ movement.is_negative ? '' : '+' }}{{ movement.quantity }}
                                </template>
                            </div>
                            <div class="text-xs tabular-nums text-gray-400 flex items-center justify-end" v-tooltip="ctrans('Running quantity')">
                                <template v-if="movement.running_quantity_org_stock_fractional">
                                    <template v-if="movement.is_running_negative">−</template>
                                    <FractionDisplay :fractionData="movement.running_quantity_org_stock_fractional" />
                                </template>
                                <template v-else>
                                    {{ movement.running_quantity_org_stock }}
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: add or edit the org stock barcode by typing or scanning into the input -->
        <Modal :isOpen="isBarcodeModalOpen" @onClose="isBarcodeModalOpen = false" width="w-full max-w-md">
            <div class="flex flex-col gap-4 p-2">
                <div class="flex justify-between items-center">
                    <div class="text-lg font-semibold">{{ editingLevel === "unit" ? ctrans("Unit EAN13 barcode") : ctrans("SKO (outer packing) barcode") }}</div>
                    <div class="text-3xl">
                        <FontAwesomeIcon icon='fal fa-barcode' class='' fixed-width aria-hidden='true' />
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    {{ ctrans("Type the barcode, or click the field and scan the item with a barcode scanner.") }}
                </div>
                <input
                    ref="barcodeInputElement"
                    v-model="barcodeInput"
                    type="text"
                    autocomplete="off"
                    spellcheck="false"
                    maxlength="54"
                    :placeholder="ctrans('e.g. 5055796528387')"
                    class="w-full rounded-md border-gray-300 py-2 px-3 font-mono text-lg tracking-wide focus:border-indigo-500 focus:ring-indigo-500"
                    @keydown.enter.prevent="saveBarcode(barcodeInput.trim() || null)"
                />
                <div class="flex justify-between gap-2">
                    <Button
                        v-if="editingHasNumber"
                        type="negative"
                        :label="ctrans('Remove barcode')"
                        icon="fal fa-trash-alt"
                        :loading="isSavingBarcode"
                        @click="saveBarcode(null)" />
                    <div class="ml-auto flex gap-2 w-full">
                        <Button type="tertiary" :label="ctrans('Cancel')" @click="isBarcodeModalOpen = false" />
                        <Button
                            :label="ctrans('Save')"
                            :loading="isSavingBarcode"
                            full
                            :disabled="!barcodeInput.trim()"
                            @click="saveBarcode(barcodeInput.trim())" />
                    </div>
                </div>
            </div>
        </Modal>

        <ConfirmDialog />
    </div>
</template>
