<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, nextTick, onMounted, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faBarcode,
    faBoxOpen,
    faCamera,
    faChair,
    faCube,
    faCubes,
    faEdit,
    faExclamationTriangle,
    faExternalLink,
    faLaugh,
    faMoneyBill,
    faPallet,
    faPeopleArrows,
    faPlus,
    faPersonDolly,
    faRulerCombined,
    faSeedling,
    faTrashAlt,
    faWeightHanging,
} from "@fal"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"
import Image from "@common/Components/Image.vue"
import Icon from "@/Components/Icon.vue"
import BoxDisplay from "@/Components/DataDisplay/BoxDisplay.vue"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import OrgStockLabelModal from "@/Components/Warehouse/Inventory/OrgStockLabelModal.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import JsBarcode from "jsbarcode"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"

library.add(
    faBarcode,
    faBoxOpen,
    faCamera,
    faChair,
    faCube,
    faCubes,
    faEdit,
    faExclamationTriangle,
    faExternalLink,
    faLaugh,
    faMoneyBill,
    faPallet,
    faPeopleArrows,
    faPersonDolly,
    faPlus,
    faRulerCombined,
    faSeedling,
    faTrashAlt,
    faWeightHanging,
)

type Barcode = {
    level: string
    label: string
    number: string | null
    weight: number | null
    dimensions: {
        h?: number
        l?: number
        w?: number
        type?: string
        units?: string
    } | null
    warning?: string | null
}

const props = defineProps<{
    data: {
        product: {
            code: string
            name: string | null
            description: string | null
            image: object | null
            state: {
                label: string
                icon: { icon: string; class?: string; tooltip?: string }
            }
            is_available: boolean
            composition: string | null
            route: routeType | null
        }
        costs: {
            currency_code: string | null
            unit_cost: number
            extra_costs_percentage: number
            delivered_unit_cost: number
            pack_cost: number | null
            carton_cost: number | null
        }
        packaging: {
            units_per_pack: number | null
            units_per_carton: number | null
            cbm: number | null
        }
        trade_units: {
            slug: string
            code: string
            name: string | null
            unit: string | null
            units: number | string
            image: object | null
            route: routeType
        }[]
        stocks: {
            slug: string
            code: string
            name: string | null
            route: routeType
        }[]
        composition: {
            code: string
            name: string | null
            slug: string
            quantity: number
            route: routeType
            org_stocks: {
                code: string
                slug: string
                quantity: number
                organisation: { code: string; slug: string }
                route: routeType
            }[]
        }[]
        parties: {
            label: string
            icon: string
            name: string | null
            code: string | null
            image: object | null
            route: routeType
        }[]
        organisation?: {
            name: string
            code: string
            state: string
            is_available: boolean
        }
        supplierProductInfo?: {
            minimum_carton_order?: number
            delivery_time?: number
            unit_expense?: number
            extra_costs?: number
            barcode?: string
            net_weight?: number
            gross_weight?: number
            marketing_dimensions?: Record<string, any>
        }
        stats: {
            label: string
            count: number
            description?: string
            full?: boolean
        }[]
        barcodes?: Barcode[]
        label_org_stocks?: {
            id: number
            code: string
            organisation_code: string
            barcodes: Barcode[]
            label_options_route: routeType
            carton_barcode_update_route: routeType | null
        }[]
        carton?: {
            supplier_product_id: number
            net_weight: number | null
            gross_weight: number | null
            update_route: routeType | null
        }
        internal_images?: {
            upload_route: routeType | null
            images: {
                id: number
                is_main: boolean
                image: object
                detach_route: routeType | null
            }[]
        }
    }
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (value: number | null) =>
    value === null || value === undefined ? "-" : locale.currencyFormat(props.data.costs.currency_code, value)

const costRows = computed(() => [
    { label: ctrans("Unit cost"), value: money(props.data.costs.unit_cost), strong: true },
    { label: ctrans("Extra costs"), value: `${locale.number(props.data.costs.extra_costs_percentage)} %` },
    { label: ctrans("Delivered unit cost"), value: money(props.data.costs.delivered_unit_cost), strong: true },
    { label: ctrans("Delivered pack cost"), value: money(props.data.costs.pack_cost) },
    { label: ctrans("Delivered carton cost"), value: money(props.data.costs.carton_cost) },
])

const packagingRows = computed(() => [
    {
        label: ctrans("Units per pack"),
        value: props.data.packaging.units_per_pack ? locale.number(props.data.packaging.units_per_pack) : "-",
    },
    {
        label: ctrans("Units per carton"),
        value: props.data.packaging.units_per_carton ? locale.number(props.data.packaging.units_per_carton) : "-",
    },
    {
        label: ctrans("Carton volume"),
        value: props.data.packaging.cbm ? `${locale.number(props.data.packaging.cbm)} m³` : "-",
    },
])

const supplyingRows = computed(() => {
    const info = props.data.supplierProductInfo

    if (!info) {
        return []
    }

    return [
        {
            key: "minimum_carton_order",
            label: ctrans("Minimum order"),
            value: info.minimum_carton_order ? ctrans(":count cartons", { count: info.minimum_carton_order }) : null,
        },
        {
            key: "delivery_time",
            label: ctrans("Delivery time"),
            value: info.delivery_time ? ctrans(":days days", { days: info.delivery_time }) : null,
        },
        {
            key: "unit_expense",
            label: ctrans("Unit expense"),
            value: info.unit_expense ? money(info.unit_expense) : null,
        },
        {
            key: "barcode",
            label: ctrans("Barcode"),
            value: info.barcode,
        },
        {
            key: "net_weight",
            label: ctrans("Net weight"),
            value: info.net_weight ? `${locale.number(info.net_weight)} g` : null,
        },
        {
            key: "gross_weight",
            label: ctrans("Gross weight"),
            value: info.gross_weight ? `${locale.number(info.gross_weight)} g` : null,
        },
    ].filter((row) => row.value)
})

const barcodeLevelLabels = computed<Record<string, string>>(() => ({
    sko: ctrans("SKO"),
    unit: ctrans("Unit"),
    carton: ctrans("Carton"),
}))

const formatWeight = (grams: number | null) => {
    if (!grams || grams <= 0) {
        return null
    }

    return grams >= 1000 ? `${locale.number(grams / 1000)} kg` : `${locale.number(grams)} g`
}

const formatDimensions = (dimensions: { h?: number; l?: number; w?: number; units?: string } | null) => {
    if (!dimensions || (!dimensions.l && !dimensions.w && !dimensions.h)) {
        return null
    }

    const sides = [dimensions.l, dimensions.w, dimensions.h].map((side) => (side ? locale.number(side) : "-"))

    return `${sides.join(" × ")} ${dimensions.units ?? ""}`.trim()
}

const labelOrgStocks = computed(() => props.data.label_org_stocks ?? [])
const selectedLabelOrgStockId = ref<number | null>(labelOrgStocks.value[0]?.id ?? null)

const shownBarcodes = computed<Barcode[]>(() =>
    labelOrgStocks.value.find((orgStock) => orgStock.id === selectedLabelOrgStockId.value)?.barcodes ?? props.data.barcodes ?? [])

const renderBarcodes = () => {
    shownBarcodes.value.forEach((barcode) => {
        if (!barcode.number) {
            return
        }

        JsBarcode("#supplier-product-barcode-" + barcode.level, barcode.number, {
            format: /^\d{13}$/.test(barcode.number) ? "EAN13" : "CODE128",
            lineColor: "#000",
            width: 2,
            height: 50,
            displayValue: true,
        })
    })
}

onMounted(() => nextTick(renderBarcodes))
watch(selectedLabelOrgStockId, () => nextTick(renderBarcodes))

const isPrintable = (barcode: { level: string; number: string | null }) =>
    !!barcode.number && selectedLabelOrgStockId.value !== null

const isLabelModalOpen = ref(false)
const isLoadingLabelOptions = ref(false)
const labelLevel = ref("unit")
const labelOptions = ref<any>(null)
const labelRoute = ref<routeType | null>(null)

const openLabelModal = async (level: string) => {
    const orgStock = labelOrgStocks.value.find((item) => item.id === selectedLabelOrgStockId.value)

    if (!orgStock || isLoadingLabelOptions.value) {
        return
    }

    isLoadingLabelOptions.value = true

    try {
        const { data } = await axios.get(route(orgStock.label_options_route.name, {
            ...orgStock.label_options_route.parameters,
            ...(props.data.carton ? { supplier_product: props.data.carton.supplier_product_id } : {}),
        }))

        labelOptions.value = data.options
        labelRoute.value = props.data.carton
            ? { ...data.label_route, parameters: { ...data.label_route.parameters, supplier_product: props.data.carton.supplier_product_id } }
            : data.label_route
        labelLevel.value = level
        isLabelModalOpen.value = true
    } catch (error) {
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans("Could not load the label options"),
            type: "error",
        })
    } finally {
        isLoadingLabelOptions.value = false
    }
}

const gramsToKilograms = (grams: number | null | undefined) => (grams ? String(grams / 1000) : "")

const kilogramsToGrams = (kilograms: string): number | null => {
    const value = kilograms.trim().replace(",", ".")

    return value === "" ? null : Math.round(Number(value) * 1000)
}

const cartonNetWeight = ref("")
const cartonGrossWeight = ref("")
const isSavingCartonWeights = ref(false)

watch(() => props.data.carton, (carton) => {
    cartonNetWeight.value = gramsToKilograms(carton?.net_weight)
    cartonGrossWeight.value = gramsToKilograms(carton?.gross_weight)
}, { immediate: true })

const isValidKilograms = (kilograms: string) => {
    const grams = kilogramsToGrams(kilograms)

    return grams === null || (Number.isFinite(grams) && grams >= 0)
}

const canSaveCartonWeights = computed(() =>
    isValidKilograms(cartonNetWeight.value)
    && isValidKilograms(cartonGrossWeight.value)
    && (kilogramsToGrams(cartonNetWeight.value) !== (props.data.carton?.net_weight ?? null)
        || kilogramsToGrams(cartonGrossWeight.value) !== (props.data.carton?.gross_weight ?? null)))

const saveCartonWeights = async () => {
    const updateRoute = props.data.carton?.update_route

    if (!updateRoute || !canSaveCartonWeights.value || isSavingCartonWeights.value) {
        return
    }

    isSavingCartonWeights.value = true

    try {
        await axios.patch(route(updateRoute.name, updateRoute.parameters), {
            carton_net_weight: kilogramsToGrams(cartonNetWeight.value),
            carton_weight: kilogramsToGrams(cartonGrossWeight.value),
        })
        router.reload({ only: ["showcase"] })
        notify({
            title: ctrans("Saved"),
            text: ctrans("Carton weights updated"),
            type: "success",
        })
    } catch (error) {
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans("Could not save the carton weights"),
            type: "error",
        })
    } finally {
        isSavingCartonWeights.value = false
    }
}

const cartonBarcodeUpdateRoute = computed(() =>
    labelOrgStocks.value.find((orgStock) => orgStock.id === selectedLabelOrgStockId.value)?.carton_barcode_update_route ?? null)

const isCartonBarcodeModalOpen = ref(false)
const cartonBarcodeInput = ref("")
const cartonBarcodeHasNumber = ref(false)
const isSavingCartonBarcode = ref(false)
const cartonBarcodeInputElement = ref<HTMLInputElement | null>(null)

const openCartonBarcodeModal = (barcode: Barcode) => {
    cartonBarcodeInput.value = barcode.number ?? ""
    cartonBarcodeHasNumber.value = !!barcode.number
    isCartonBarcodeModalOpen.value = true
    nextTick(() => cartonBarcodeInputElement.value?.focus())
}

const saveCartonBarcode = async (value: string | null) => {
    const updateRoute = cartonBarcodeUpdateRoute.value

    if (!updateRoute || isSavingCartonBarcode.value) {
        return
    }

    isSavingCartonBarcode.value = true

    try {
        await axios.patch(route(updateRoute.name, updateRoute.parameters), { carton_barcode: value })
        isCartonBarcodeModalOpen.value = false
        router.reload({ only: ["showcase"], onSuccess: () => nextTick(renderBarcodes) })
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.errors?.carton_barcode?.[0] ?? ctrans("Could not save the barcode"),
            type: "error",
        })
    } finally {
        isSavingCartonBarcode.value = false
    }
}

const isUploadingImages = ref(false)

const uploadInternalImages = async (event: Event) => {
    const input = event.target as HTMLInputElement
    const uploadRoute = props.data.internal_images?.upload_route

    if (!uploadRoute || !input.files?.length) {
        return
    }

    const formData = new FormData()
    Array.from(input.files).forEach((file) => formData.append("images[]", file))

    isUploadingImages.value = true

    try {
        await axios.post(route(uploadRoute.name, uploadRoute.parameters), formData)
        router.reload({ only: ["showcase"] })
    } catch (error) {
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans("Could not upload the pictures"),
            type: "error",
        })
    } finally {
        isUploadingImages.value = false
        input.value = ""
    }
}

const removeInternalImage = (detachRoute: routeType) => {
    router.delete(route(detachRoute.name, detachRoute.parameters), {
        preserveScroll: true,
        only: ["showcase"],
    })
}

const availabilityBadge = (isAvailable: boolean) =>
    isAvailable
        ? { label: ctrans("Available"), class: "bg-green-50 text-green-700 ring-green-600/20" }
        : { label: ctrans("Not available"), class: "bg-red-50 text-red-700 ring-red-600/20" }
</script>

<template>
    <div class="grid gap-6 p-6 md:grid-cols-3">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex gap-4">
                <Image v-if="data.product.image" :src="data.product.image" class="h-28 w-28 shrink-0" />
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <Icon :data="data.product.state.icon" v-tooltip="data.product.state.label" />
                        <span class="truncate text-lg font-semibold text-gray-800">{{ data.product.code }}</span>
                    </div>
                    <div class="mt-1 text-sm text-gray-600">{{ data.product.name }}</div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                            :class="availabilityBadge(data.product.is_available).class">
                            {{ availabilityBadge(data.product.is_available).label }}
                        </span>
                        <span v-if="data.product.composition"
                            class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-600">
                            {{ data.product.composition.replace("_", " ") }}
                        </span>
                    </div>
                </div>
            </div>

            <p v-if="data.product.description" class="mt-4 whitespace-pre-wrap text-sm text-gray-500">
                {{ data.product.description }}
            </p>

            <div v-if="data.internal_images && (data.internal_images.images.length || data.internal_images.upload_route)"
                class="mt-4 border-t border-gray-100 pt-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400"
                        v-tooltip="ctrans('Only for internal use, these pictures never reach the websites')">
                        {{ ctrans("Internal pictures") }}
                    </div>
                    <label v-if="data.internal_images.upload_route"
                        class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-medium text-gray-600 hover:text-gray-900"
                        :class="{ 'pointer-events-none opacity-50': isUploadingImages }">
                        <Icon :data="{ icon: 'fal fa-camera' }" />
                        {{ isUploadingImages ? ctrans("Uploading") : ctrans("Add picture") }}
                        <input type="file" accept="image/*" multiple class="hidden" @change="uploadInternalImages" />
                    </label>
                </div>
                <div v-if="data.internal_images.images.length" class="mt-2 flex flex-wrap gap-2">
                    <div v-for="internalImage in data.internal_images.images" :key="internalImage.id"
                        class="group relative h-16 w-16 overflow-hidden rounded border"
                        :class="internalImage.is_main ? 'border-gray-500' : 'border-gray-200'">
                        <Image :src="internalImage.image" class="h-full w-full" />
                        <button v-if="internalImage.detach_route" type="button"
                            class="absolute right-0.5 top-0.5 hidden rounded bg-white/90 px-1 text-xs text-red-500 group-hover:block"
                            v-tooltip="ctrans('Remove picture')"
                            @click="removeInternalImage(internalImage.detach_route)">
                            <Icon :data="{ icon: 'fal fa-trash-alt' }" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="data.organisation" class="mt-4 border-t border-gray-100 pt-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    {{ ctrans("In") }} {{ data.organisation.name }}
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                        :class="availabilityBadge(data.organisation.is_available).class">
                        {{ availabilityBadge(data.organisation.is_available).label }}
                    </span>
                    <span
                        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-600">
                        {{ data.organisation.state.replace("_", " ") }}
                    </span>
                </div>
            </div>

            <Link v-if="data.product.route" :href="route(data.product.route.name, data.product.route.parameters)"
                class="mt-4 inline-flex items-center gap-1.5 text-xs font-medium text-indigo-500 hover:text-indigo-700">
                <Icon :data="{ icon: 'fal fa-external-link' }" />
                {{ ctrans("Supplier product in supply chain") }}
            </Link>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-money-bill' }" />
                {{ ctrans("Costs") }}
            </h3>
            <dl class="divide-y divide-gray-100">
                <div v-for="row in costRows" :key="row.label" class="flex items-baseline justify-between py-2">
                    <dt class="text-sm text-gray-500">{{ row.label }}</dt>
                    <dd class="tabular-nums" :class="row.strong ? 'text-base font-semibold text-gray-800' : 'text-sm text-gray-700'">
                        {{ row.value }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-pallet' }" />
                {{ ctrans("Packaging") }}
            </h3>
            <dl class="divide-y divide-gray-100">
                <div v-for="row in packagingRows" :key="row.label" class="flex items-baseline justify-between py-2">
                    <dt class="text-sm text-gray-500">{{ row.label }}</dt>
                    <dd class="text-sm tabular-nums text-gray-700">{{ row.value }}</dd>
                </div>
            </dl>

            <template v-if="supplyingRows.length">
                <h3 class="mb-3 mt-5 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <Icon :data="{ icon: 'fal fa-truck-container' }" />
                    {{ ctrans("Supplying") }}
                </h3>
                <dl class="divide-y divide-gray-100">
                    <div v-for="row in supplyingRows" :key="row.key" class="flex items-baseline justify-between py-2">
                        <dt class="text-sm text-gray-500">{{ row.label }}</dt>
                        <dd class="text-sm tabular-nums text-gray-700">{{ row.value }}</dd>
                    </div>
                </dl>
            </template>

            <template v-if="data.parties.length">
                <h3 class="mb-3 mt-5 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <Icon :data="{ icon: 'fal fa-person-dolly' }" />
                    {{ ctrans("Provided by") }}
                </h3>
                <div class="flex flex-col gap-2">
                    <component :is="party.route ? Link : 'div'" v-for="party in data.parties" :key="party.label"
                        :href="party.route ? route(party.route.name, party.route.parameters) : undefined"
                        class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-gray-300 hover:bg-gray-50">
                        <Image v-if="party.image" :src="party.image" class="h-8 w-8 shrink-0" />
                        <Icon v-else :data="{ icon: party.icon }" class="w-8 shrink-0 text-center text-gray-400" />
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wide text-gray-400">{{ party.label }}</div>
                            <div class="truncate text-sm font-medium text-gray-700">{{ party.name }}</div>
                        </div>
                        <span class="ml-auto text-xs tabular-nums text-gray-400">{{ party.code }}</span>
                    </component>
                </div>
            </template>
        </section>

        <section v-if="shownBarcodes.length" class="md:col-span-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <h3 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <Icon :data="{ icon: 'fal fa-barcode' }" />
                    {{ ctrans("Barcodes") }}
                </h3>
                <div v-if="labelOrgStocks.length > 1" class="ml-auto flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-gray-400">{{ ctrans("SKO of") }}</span>
                    <button v-for="orgStock in labelOrgStocks" :key="orgStock.id" type="button"
                        v-tooltip="orgStock.code"
                        class="rounded-full px-2 py-0.5 font-medium ring-1 ring-inset transition"
                        :class="selectedLabelOrgStockId === orgStock.id
                            ? 'bg-[--app-accent] text-white ring-[--app-accent]'
                            : 'bg-white text-gray-600 ring-gray-300 hover:ring-[--app-accent]'"
                        @click="selectedLabelOrgStockId = orgStock.id">
                        {{ orgStock.organisation_code }}
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 xl:grid-cols-[auto_auto_auto_1fr] xl:items-center xl:gap-x-4 xl:gap-y-3">
                <template v-for="barcode in shownBarcodes" :key="selectedLabelOrgStockId + '-' + barcode.level">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-gray-100 p-3 xl:contents">
                        <div class="w-12 shrink-0 text-sm font-medium uppercase tracking-wide text-gray-500"
                            v-tooltip="ctrans(barcode.label)">
                            {{ barcodeLevelLabels[barcode.level] }}
                        </div>

                        <button v-if="isPrintable(barcode)"
                            type="button"
                            v-tooltip="ctrans('Print PDF label')"
                            class="min-w-0 max-w-full justify-self-start transition hover:opacity-60 disabled:cursor-wait"
                            :disabled="isLoadingLabelOptions"
                            @click="openLabelModal(barcode.level)">
                            <svg :id="'supplier-product-barcode-' + barcode.level" class="h-14 max-w-full"></svg>
                        </button>
                        <svg v-else-if="barcode.number" :id="'supplier-product-barcode-' + barcode.level" class="h-14 max-w-full justify-self-start"></svg>
                        <button v-else-if="barcode.level === 'carton' && cartonBarcodeUpdateRoute"
                            type="button"
                            class="flex h-14 max-w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-2 text-left text-sm text-gray-400 transition hover:border-[--app-accent] hover:text-[--app-accent]"
                            @click="openCartonBarcodeModal(barcode)">
                            <Icon :data="{ icon: 'fal fa-plus' }" />
                            {{ ctrans("Add barcode (type or scan it)") }}
                        </button>
                        <div v-else class="flex h-14 items-center text-sm italic text-gray-400">{{ ctrans("No barcode") }}</div>

                        <div v-if="barcode.level === 'carton' && data.carton"
                            class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-700 xl:col-span-2">
                            <button v-if="barcode.number && cartonBarcodeUpdateRoute"
                                type="button"
                                v-tooltip="ctrans('Edit barcode')"
                                class="text-gray-300 transition hover:text-[--app-accent]"
                                @click="openCartonBarcodeModal(barcode)">
                                <Icon :data="{ icon: 'fal fa-edit' }" />
                            </button>
                            <Icon :data="{ icon: 'fal fa-weight-hanging' }" class="w-4 shrink-0 text-gray-400" />
                            <template v-if="data.carton.update_route">
                                <label class="inline-flex items-center gap-1.5">
                                    <span class="text-xs font-medium text-gray-500" v-tooltip="ctrans('Net weight')">{{ ctrans("NW") }}</span>
                                    <input v-model="cartonNetWeight" type="text" inputmode="decimal"
                                        class="w-20 rounded-md border-gray-300 py-1 px-2 text-sm tabular-nums focus:border-[--app-accent] focus:ring-[--app-accent]"
                                        :class="isValidKilograms(cartonNetWeight) ? '' : 'border-red-400'"
                                        @keydown.enter="saveCartonWeights" />
                                    <span class="text-xs text-gray-400">kg</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5">
                                    <span class="text-xs font-medium text-gray-500" v-tooltip="ctrans('Gross weight')">{{ ctrans("GW") }}</span>
                                    <input v-model="cartonGrossWeight" type="text" inputmode="decimal"
                                        class="w-20 rounded-md border-gray-300 py-1 px-2 text-sm tabular-nums focus:border-[--app-accent] focus:ring-[--app-accent]"
                                        :class="isValidKilograms(cartonGrossWeight) ? '' : 'border-red-400'"
                                        @keydown.enter="saveCartonWeights" />
                                    <span class="text-xs text-gray-400">kg</span>
                                </label>
                                <button v-if="canSaveCartonWeights" type="button"
                                    class="rounded border border-[--app-accent] bg-[--app-accent] px-2 py-1 text-xs text-[--app-accent-text] transition hover:opacity-80 disabled:cursor-wait disabled:opacity-60"
                                    :disabled="isSavingCartonWeights"
                                    @click="saveCartonWeights">
                                    {{ ctrans("Save") }}
                                </button>
                            </template>
                            <template v-else>
                                <span v-tooltip="ctrans('Net weight')">{{ ctrans("NW") }} <span class="font-medium tabular-nums">{{ formatWeight(data.carton.net_weight) ?? "—" }}</span></span>
                                <span v-tooltip="ctrans('Gross weight')">{{ ctrans("GW") }} <span class="font-medium tabular-nums">{{ formatWeight(data.carton.gross_weight) ?? "—" }}</span></span>
                            </template>
                        </div>

                        <template v-else>
                            <span v-if="formatWeight(barcode.weight)"
                                class="inline-flex items-center gap-2 whitespace-nowrap text-sm text-gray-700">
                                <Icon :data="{ icon: 'fal fa-weight-hanging' }" class="w-4 shrink-0 text-gray-400" />
                                <span class="font-medium tabular-nums">{{ formatWeight(barcode.weight) }}</span>
                            </span>
                            <span v-else class="hidden text-sm text-gray-300 xl:inline">—</span>

                            <span v-if="formatDimensions(barcode.dimensions)"
                                v-tooltip="ctrans('Dimensions (L × W × H)')"
                                class="inline-flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-700">
                                <Icon :data="{ icon: 'fal fa-ruler-combined' }" class="w-4 shrink-0 text-gray-400" />
                                <span class="whitespace-nowrap font-medium tabular-nums">{{ formatDimensions(barcode.dimensions) }}</span>
                                <span v-if="barcode.dimensions?.type"
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-500">
                                    {{ barcode.dimensions.type }}
                                </span>
                            </span>
                            <span v-else-if="barcode.warning"
                                class="inline-flex min-w-0 items-start gap-2 text-xs leading-snug text-amber-700">
                                <Icon :data="{ icon: 'fal fa-exclamation-triangle' }" class="mt-0.5 w-4 shrink-0 text-amber-500" />
                                <span>{{ barcode.warning }}</span>
                            </span>
                            <span v-else class="hidden text-sm text-gray-300 xl:inline">—</span>
                        </template>
                    </div>
                </template>
            </div>
        </section>

        <section v-if="data.trade_units.length" class="md:col-span-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-cubes' }" />
                {{ ctrans("Trade units") }}
            </h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <component :is="tradeUnit.route ? Link : 'div'" v-for="tradeUnit in data.trade_units" :key="tradeUnit.slug"
                    :href="tradeUnit.route ? route(tradeUnit.route.name, tradeUnit.route.parameters) : undefined"
                    class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-gray-300 hover:bg-gray-50">
                    <Image v-if="tradeUnit.image" :src="tradeUnit.image" class="h-12 w-12 shrink-0" />
                    <Icon v-else :data="{ icon: 'fal fa-cube' }" class="w-12 shrink-0 text-center text-gray-300" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-gray-700">{{ tradeUnit.name }}</div>
                        <div class="truncate text-xs text-gray-400">{{ tradeUnit.code }}</div>
                    </div>
                    <ProductUnitLabel :units="tradeUnit.units" :unit="tradeUnit.unit ?? undefined" />
                </component>
            </div>
        </section>

        <!-- Same triangle as the master product composition page, read-only here -->
        <section v-if="data.composition.length" class="md:col-span-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-cubes' }" />
                {{ ctrans("Composition") }}
            </h3>
            <div class="flex flex-col gap-4">
                <div v-for="tradeUnit in data.composition" :key="tradeUnit.slug">
                    <component :is="tradeUnit.route ? Link : 'div'" :href="tradeUnit.route ? route(tradeUnit.route.name, tradeUnit.route.parameters) : undefined"
                        class="text-sm font-medium text-gray-700 hover:text-indigo-600">
                        {{ tradeUnit.code }} <span class="text-gray-400 font-normal">{{ tradeUnit.name }}</span>
                    </component>
                    <div v-if="tradeUnit.org_stocks.length" class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <component :is="orgStock.route ? Link : 'div'" v-for="orgStock in tradeUnit.org_stocks" :key="orgStock.slug"
                            :href="orgStock.route ? route(orgStock.route.name, orgStock.route.parameters) : undefined"
                            class="flex items-baseline justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 text-xs transition hover:border-gray-300 hover:bg-gray-50">
                            <span class="truncate text-gray-700">{{ orgStock.code }}</span>
                            <span class="shrink-0 text-gray-400">{{ orgStock.organisation.code }}</span>
                        </component>
                    </div>
                    <p v-else class="mt-1 text-xs text-gray-400">{{ ctrans("No org stock linked") }}</p>
                </div>
            </div>
        </section>

        <section v-if="data.stocks.length" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-box-open' }" />
                {{ ctrans("SKUs") }}
            </h3>
            <div class="flex flex-col divide-y divide-gray-100">
                <component :is="stock.route ? Link : 'div'" v-for="stock in data.stocks" :key="stock.slug"
                    :href="stock.route ? route(stock.route.name, stock.route.parameters) : undefined"
                    class="flex items-baseline justify-between gap-3 py-2 hover:text-indigo-600">
                    <span class="truncate text-sm text-gray-700">{{ stock.name }}</span>
                    <span class="shrink-0 text-xs tabular-nums text-gray-400">{{ stock.code }}</span>
                </component>
            </div>
        </section>

        <section v-if="data.stats.length" class="md:col-span-3">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                {{ ctrans("Statistics") }}
            </h3>
            <BoxDisplay :data="data.stats" />
        </section>

        <Modal :isOpen="isCartonBarcodeModalOpen" @onClose="isCartonBarcodeModalOpen = false" width="w-full max-w-md">
            <div class="flex flex-col gap-4 p-2">
                <div class="text-lg font-semibold">{{ ctrans("Carton barcode") }}</div>
                <div class="text-sm text-gray-500">
                    {{ ctrans("Type the barcode, or click the field and scan the carton with a barcode scanner. It is shared by every organisation that buys this stock.") }}
                </div>
                <input
                    ref="cartonBarcodeInputElement"
                    v-model="cartonBarcodeInput"
                    type="text"
                    autocomplete="off"
                    spellcheck="false"
                    maxlength="64"
                    :placeholder="ctrans('e.g. 5056368317972')"
                    class="w-full rounded-md border-gray-300 py-2 px-3 font-mono text-lg tracking-wide focus:border-[--app-accent] focus:ring-[--app-accent]"
                    @keydown.enter.prevent="cartonBarcodeInput.trim() && saveCartonBarcode(cartonBarcodeInput.trim())" />
                <div class="flex justify-between gap-2">
                    <Button
                        v-if="cartonBarcodeHasNumber"
                        type="negative"
                        :label="ctrans('Remove barcode')"
                        icon="fal fa-trash-alt"
                        :loading="isSavingCartonBarcode"
                        @click="saveCartonBarcode(null)" />
                    <div class="ml-auto flex w-full gap-2">
                        <Button type="tertiary" :label="ctrans('Cancel')" @click="isCartonBarcodeModalOpen = false" />
                        <Button
                            :label="ctrans('Save')"
                            :loading="isSavingCartonBarcode"
                            full
                            :disabled="!cartonBarcodeInput.trim()"
                            @click="saveCartonBarcode(cartonBarcodeInput.trim())" />
                    </div>
                </div>
            </div>
        </Modal>

        <OrgStockLabelModal
            v-if="labelOptions && labelRoute"
            :isOpen="isLabelModalOpen"
            :level="labelLevel"
            :labelRoute="labelRoute"
            :options="labelOptions"
            @onClose="isLabelModalOpen = false" />
    </div>
</template>
