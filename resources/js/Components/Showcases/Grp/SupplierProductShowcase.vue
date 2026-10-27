<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, nextTick, onMounted, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { barcodePackContents } from "@/Composables/barcodePackContents"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faBarcode,
    faBoxOpen,
    faCamera,
    faChair,
    faCheck,
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
    faTimes,
    faTrashAlt,
    faWeightHanging,
} from "@fal"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"
import Image from "@common/Components/Image.vue"
import Icon from "@/Components/Icon.vue"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import OrgStockLabelModal from "@/Components/Warehouse/Inventory/OrgStockLabelModal.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import CopyButton from "@/Components/Utils/CopyButton.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import JsBarcode from "jsbarcode"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import ProductImageViewerDialog from "@/Components/Product/ProductImageViewerDialog.vue"
import AgentSupplierProductPanel from "@/Components/Procurement/AgentSupplierProductPanel.vue"
import CompositionTriangle from "@/Components/Goods/CompositionTriangle.vue"

library.add(
    faBarcode,
    faBoxOpen,
    faCamera,
    faChair,
    faCheck,
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
    faTimes,
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
    editable?: boolean
    quantity?: number | null
    packs?: number | null
}

const props = defineProps<{
    data: {
        product: {
            code: string
            name: string | null
            description: string | null
            image: object | null
            original_url: string | null
            download_url: string | null
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
            extra_costs_percentage?: number
            delivered_unit_cost?: number
            pack_cost?: number | null
            carton_cost?: number | null
        }
        agent?: InstanceType<typeof AgentSupplierProductPanel>["$props"]["panel"]
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
        triangle: {
            trade_units: InstanceType<typeof CompositionTriangle>["$props"]["tradeUnits"]
            products_count: number
            mood: "angry" | "crying" | null
            warning: string | null
            trade_unit: { code: string; route: routeType | null }
            stock: { code: string; route: routeType | null }
            composition_route: routeType | null
        } | null
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
            organisation_id: number
            organisation_code: string
            barcodes: Barcode[]
            label_options_route: routeType
            carton_barcode_update_route: routeType | null
            sko_barcode_update_route: routeType | null
        }[]
        selected_label_org_stock_id?: number | null
        label_organisation?: {
            id: number
            code: string
            name: string
            has_org_stock: boolean
        }
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
                original_url: string
                download_url: string
                detach_route: routeType | null
            }[]
        }
    }
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (value: number | null) =>
    value === null || value === undefined ? "-" : locale.currencyFormat(props.data.costs.currency_code, value)

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
const selectedLabelOrgStockId = ref<number | null>(props.data.selected_label_org_stock_id ?? labelOrgStocks.value[0]?.id ?? null)

const labelOrgStockOptions = computed(() =>
    labelOrgStocks.value.map((orgStock) => ({ label: orgStock.organisation_code, value: String(orgStock.id) })))

const selectedLabelOrgStockOption = computed<string | undefined>({
    get: () => (selectedLabelOrgStockId.value === null ? undefined : String(selectedLabelOrgStockId.value)),
    set: (value) => {
        selectedLabelOrgStockId.value = value ? Number(value) : null
    },
})

const selectedLabelOrgStock = computed(() =>
    labelOrgStocks.value.find((orgStock) => orgStock.id === selectedLabelOrgStockId.value) ?? null)

const labelOrganisationNotice = computed(() => {
    const labelOrganisation = props.data.label_organisation

    if (!labelOrganisation || labelOrganisation.has_org_stock || !selectedLabelOrgStock.value) {
        return null
    }

    return ctrans(":organisation has no SKO of this product, so these barcodes and labels are :sko_organisation's. The carton label lets you pick who it is commercialised by.", {
        organisation: labelOrganisation.code,
        sko_organisation: selectedLabelOrgStock.value.organisation_code,
    })
})

const labelQuery = computed(() => props.data.carton ? { supplier_product: props.data.carton.supplier_product_id } : {})

const shownBarcodes = computed<Barcode[]>(() =>
    labelOrgStocks.value.find((orgStock) => orgStock.id === selectedLabelOrgStockId.value)?.barcodes ?? props.data.barcodes ?? [])

const ean13CheckDigit = (number: string) => {
    const weightedSum = number
        .slice(0, 12)
        .split("")
        .reduce((sum, digit, index) => sum + Number(digit) * (index % 2 ? 3 : 1), 0)

    return String((10 - (weightedSum % 10)) % 10)
}

const isValidEan13 = (number: string) => /^\d{13}$/.test(number) && ean13CheckDigit(number) === number[12]

const invalidEan13Warning = (number: string | null) =>
    number && /^\d{13}$/.test(number) && !isValidEan13(number)
        ? ctrans("This is not a valid EAN13: its last digit should be :digit. Check it for a typo.", { digit: ean13CheckDigit(number) })
        : null

const renderBarcodes = () => {
    shownBarcodes.value.forEach((barcode) => {
        if (!barcode.number) {
            return
        }

        JsBarcode("#supplier-product-barcode-" + barcode.level, barcode.number, {
            format: isValidEan13(barcode.number) ? "EAN13" : "CODE128",
            lineColor: "#000",
            width: 2,
            height: 50,
            displayValue: true,
            valid: () => {},
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
            ...labelQuery.value,
            ...(props.data.label_organisation ? { commercialised_by: props.data.label_organisation.id } : {}),
        }))

        labelOptions.value = data.options
        labelRoute.value = { ...data.label_route, parameters: { ...data.label_route.parameters, ...labelQuery.value } }
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

const cartonBarcodeUpdateRoute = computed(() => selectedLabelOrgStock.value?.carton_barcode_update_route ?? null)

const skoBarcodeUpdateRoute = computed(() => selectedLabelOrgStock.value?.sko_barcode_update_route ?? null)

const barcodeUpdateRouteForLevel = (level: string): routeType | null => {
    if (level === "carton") {
        return cartonBarcodeUpdateRoute.value
    }

    return level === "sko" ? skoBarcodeUpdateRoute.value : null
}

const isSkoBarcodeNotNeeded = (barcode: Barcode): boolean => barcode.level === "sko" && Number(barcode.quantity) === 1

const canEditBarcode = (barcode: Barcode): boolean => barcode.editable !== false && !!barcodeUpdateRouteForLevel(barcode.level)

const isBarcodeModalOpen = ref(false)
const editingBarcodeLevel = ref<string>("carton")
const editingBarcodeWarning = ref<string | null>(null)
const barcodeInput = ref("")
const barcodeHasNumber = ref(false)
const barcodeError = ref<string | null>(null)
const isSavingBarcode = ref(false)
const barcodeInputElement = ref<{ $el: HTMLInputElement } | null>(null)

const openBarcodeModal = (barcode: Barcode) => {
    editingBarcodeLevel.value = barcode.level
    editingBarcodeWarning.value = barcode.warning ?? null
    barcodeInput.value = barcode.number ?? ""
    barcodeHasNumber.value = !!barcode.number
    barcodeError.value = null
    isBarcodeModalOpen.value = true
    nextTick(() => barcodeInputElement.value?.$el?.focus())
}

const saveBarcode = async (value: string | null) => {
    const updateRoute = barcodeUpdateRouteForLevel(editingBarcodeLevel.value)

    if (!updateRoute || isSavingBarcode.value) {
        return
    }

    const field = editingBarcodeLevel.value === "carton" ? "carton_barcode" : "barcode"

    isSavingBarcode.value = true
    barcodeError.value = null

    try {
        await axios.patch(route(updateRoute.name, updateRoute.parameters), { [field]: value })
        isBarcodeModalOpen.value = false
        router.reload({ only: ["showcase"], onSuccess: () => nextTick(renderBarcodes) })
    } catch (error: any) {
        barcodeError.value = error?.response?.data?.errors?.[field]?.[0]
            ?? (error?.response?.status === 403 ? ctrans("You are not allowed to change this barcode") : ctrans("Could not save the barcode"))
        nextTick(() => barcodeInputElement.value?.$el?.select())
    } finally {
        isSavingBarcode.value = false
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

const viewerImages = computed(() => {
    const internalImages = props.data.internal_images?.images ?? []

    if (internalImages.length) {
        return internalImages.map((internalImage) => ({
            source: internalImage.image,
            original_url: internalImage.original_url,
            download_url: internalImage.download_url,
            alt: props.data.product.code,
        }))
    }

    return props.data.product.image
        ? [{
            source: props.data.product.image,
            original_url: props.data.product.original_url,
            download_url: props.data.product.download_url,
            alt: props.data.product.code,
        }]
        : []
})

const isImageViewerVisible = ref(false)
const imageViewerIndex = ref(0)

const openImageViewer = (index: number) => {
    imageViewerIndex.value = index
    isImageViewerVisible.value = true
}

const openMainImageViewer = () => {
    const mainIndex = (props.data.internal_images?.images ?? []).findIndex((internalImage) => internalImage.is_main)
    openImageViewer(Math.max(mainIndex, 0))
}

const availabilityBadge = (isAvailable: boolean) =>
    isAvailable
        ? { label: ctrans("Available"), class: "bg-green-50 text-green-700 ring-green-600/20" }
        : { label: ctrans("Not available"), class: "bg-red-50 text-red-700 ring-red-600/20" }
</script>

<template>
    <div class="grid gap-6 p-6 md:grid-cols-3">
        <AgentSupplierProductPanel v-if="data.agent" :panel="data.agent" />

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex gap-4">
                <button v-if="data.product.image" type="button" class="h-28 w-28 shrink-0 cursor-zoom-in"
                    v-tooltip="ctrans('View image')" @click="openMainImageViewer">
                    <Image :src="data.product.image" class="h-full w-full" />
                </button>
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
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        {{ ctrans("Internal pictures") }}
                        <InformationIcon :information="ctrans('Only for internal use, for example printed on the stock label so the warehouse can recognise the goods. These pictures never reach the websites.')" class="text-gray-700" />
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
                    <div v-for="(internalImage, index) in data.internal_images.images" :key="internalImage.id"
                        class="group relative h-16 w-16 overflow-hidden rounded border"
                        :class="internalImage.is_main ? 'border-gray-500' : 'border-gray-200'">
                        <button type="button" class="h-full w-full cursor-zoom-in" v-tooltip="ctrans('View image')"
                            @click="openImageViewer(index)">
                            <Image :src="internalImage.image" class="h-full w-full" />
                        </button>
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
                class="mt-4 inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-gray-800">
                <Icon :data="{ icon: 'fal fa-external-link' }" />
                {{ ctrans("Supplier product in supply chain") }}
            </Link>
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

        <section v-if="data.triangle" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-cubes' }" />
                {{ ctrans("Composition") }}
            </h3>
            <CompositionTriangle
                :tradeUnits="data.triangle.trade_units"
                :productsCount="data.triangle.products_count"
                :mood="data.triangle.mood"
            />
            <p v-if="data.triangle.warning" class="mt-3 flex gap-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                <Icon :data="{ icon: 'fal fa-exclamation-triangle' }" class="mt-0.5 shrink-0" />
                {{ data.triangle.warning }}
            </p>
            <dl class="mt-3 divide-y divide-gray-100">
                <div v-for="corner in [
                    { key: 'tu', label: ctrans('Trade unit'), link: data.triangle.trade_unit },
                    { key: 'sko', label: ctrans('SKO'), link: data.triangle.stock },
                ]" :key="corner.key" class="flex items-baseline justify-between py-2">
                    <dt class="text-sm text-gray-500">{{ corner.label }}</dt>
                    <dd class="text-sm text-gray-700">
                        <Link v-if="corner.link.route" :href="route(corner.link.route.name, corner.link.route.parameters)"
                            class="hover:text-gray-900 hover:underline">
                            {{ corner.link.code }}
                        </Link>
                        <span v-else>{{ corner.link.code }}</span>
                    </dd>
                </div>
            </dl>
            <Link v-if="data.triangle.composition_route"
                :href="route(data.triangle.composition_route.name, data.triangle.composition_route.parameters)"
                class="mt-3 block">
                <Button type="secondary" icon="fal fa-edit" :label="ctrans('Edit SKO composition')" full />
            </Link>
        </section>

        <section v-if="shownBarcodes.length" class="md:col-span-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <h3 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <Icon :data="{ icon: 'fal fa-barcode' }" />
                    {{ ctrans("Barcodes") }}
                </h3>
                <div v-if="labelOrgStocks.length > 1" class="ml-auto flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-gray-500" v-tooltip="ctrans('The SKO and unit labels print the name and address of the organisation that owns the SKO')">
                        {{ ctrans("SKO of") }}
                    </span>
                    <SegmentedToggle v-model="selectedLabelOrgStockOption" :options="labelOrgStockOptions" :ariaLabel="ctrans('SKO of')" />
                </div>
            </div>
            <div v-if="labelOrganisationNotice" class="mb-3 flex items-start gap-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                <Icon :data="{ icon: 'fal fa-exclamation-triangle' }" class="mt-0.5 shrink-0" />
                <span>{{ labelOrganisationNotice }}</span>
            </div>
            <div class="grid grid-cols-1 gap-3 xl:grid-cols-[auto_auto_auto_1fr] xl:items-center xl:gap-x-4 xl:gap-y-3">
                <template v-for="barcode in shownBarcodes" :key="selectedLabelOrgStockId + '-' + barcode.level">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-gray-100 p-3 xl:contents">
                        <div class="min-w-12 shrink-0" v-tooltip="ctrans(barcode.label)">
                            <div class="text-sm font-medium uppercase tracking-wide text-gray-500">{{ barcodeLevelLabels[barcode.level] }}</div>
                            <div v-if="barcodePackContents(barcode)" class="whitespace-nowrap text-xs text-gray-400">{{ barcodePackContents(barcode) }}</div>
                        </div>

                        <div class="flex min-w-0 max-w-full items-center gap-2 justify-self-start">
                            <button v-if="isPrintable(barcode)"
                                type="button"
                                v-tooltip="ctrans('Print PDF label')"
                                class="min-w-0 max-w-full justify-self-start transition hover:opacity-60 disabled:cursor-wait"
                                :disabled="isLoadingLabelOptions"
                                @click="openLabelModal(barcode.level)">
                                <svg :id="'supplier-product-barcode-' + barcode.level" class="h-14 max-w-full"></svg>
                            </button>
                            <svg v-else-if="barcode.number" :id="'supplier-product-barcode-' + barcode.level" class="h-14 max-w-full justify-self-start"></svg>
                            <div v-else-if="isSkoBarcodeNotNeeded(barcode)" class="flex h-14 items-center text-sm italic text-gray-400">{{ ctrans("Not needed") }}</div>
                            <button v-else-if="canEditBarcode(barcode)"
                                type="button"
                                class="flex h-14 max-w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-2 text-left text-sm text-gray-400 transition hover:border-[--app-accent] hover:text-[--app-accent]"
                                @click="openBarcodeModal(barcode)">
                                <Icon :data="{ icon: 'fal fa-plus' }" />
                                {{ ctrans("Add barcode (type or scan it)") }}
                            </button>
                            <div v-else class="flex h-14 items-center text-sm italic text-gray-400">{{ ctrans("No barcode") }}</div>
                            <span v-if="barcode.number" v-tooltip="ctrans('Copy number')" class="shrink-0">
                                <CopyButton :text="barcode.number" class="h-4 !text-base text-gray-500" />
                            </span>
                            <button v-if="barcode.number && canEditBarcode(barcode)"
                                type="button"
                                v-tooltip="ctrans('Edit barcode')"
                                class="shrink-0 text-gray-300 transition hover:text-[--app-accent]"
                                @click="openBarcodeModal(barcode)">
                                <Icon :data="{ icon: 'fal fa-edit' }" />
                            </button>
                            <span v-if="barcode.number && barcode.warning" v-tooltip="barcode.warning" class="shrink-0">
                                <Icon :data="{ icon: 'fal fa-exclamation-triangle' }" class="text-amber-500" />
                            </span>
                            <span v-if="invalidEan13Warning(barcode.number)" v-tooltip="invalidEan13Warning(barcode.number)" class="shrink-0">
                                <Icon :data="{ icon: 'fal fa-exclamation-triangle' }" class="text-amber-500" />
                            </span>
                        </div>

                        <div v-if="barcode.level === 'carton' && data.carton"
                            class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-700 xl:col-span-2">
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
                        class="text-sm font-medium text-gray-700 hover:text-gray-900 hover:underline">
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
                    class="flex items-baseline justify-between gap-3 py-2 hover:text-gray-900 hover:underline">
                    <span class="truncate text-sm text-gray-700">{{ stock.name }}</span>
                    <span class="shrink-0 text-xs tabular-nums text-gray-400">{{ stock.code }}</span>
                </component>
            </div>
        </section>

        <Modal :isOpen="isBarcodeModalOpen" @onClose="isBarcodeModalOpen = false" width="w-full max-w-md">
            <div class="flex flex-col gap-4 p-2">
                <div class="text-lg font-semibold">
                    {{ editingBarcodeLevel === "carton" ? ctrans("Carton barcode") : ctrans("SKO (outer packing) barcode") }}
                    <span v-if="editingBarcodeLevel === 'sko' && selectedLabelOrgStock" class="whitespace-nowrap text-sm font-normal text-gray-500">
                        {{ selectedLabelOrgStock.organisation_code }} · {{ selectedLabelOrgStock.code }}
                    </span>
                </div>
                <div class="text-sm text-gray-500">
                    <template v-if="editingBarcodeLevel === 'carton'">
                        {{ ctrans("Type the barcode, or click the field and scan the carton with a barcode scanner. It is shared by every organisation that buys this stock.") }}
                    </template>
                    <template v-else>
                        {{ ctrans("Type the barcode, or click the field and scan the outer packing with a barcode scanner. It is what the warehouse scans, and it is shared by every organisation that holds this stock.") }}
                    </template>
                </div>
                <div v-if="editingBarcodeWarning" class="flex gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
                    <div>{{ editingBarcodeWarning }}</div>
                </div>
                <InputText
                    ref="barcodeInputElement"
                    v-model="barcodeInput"
                    autocomplete="off"
                    spellcheck="false"
                    :maxlength="editingBarcodeLevel === 'carton' ? 64 : 54"
                    :invalid="!!barcodeError"
                    :placeholder="ctrans('e.g. 5056368317972')"
                    class="w-full font-mono text-lg tracking-wide"
                    @update:modelValue="barcodeError = null"
                    @keydown.enter.prevent="barcodeInput.trim() && saveBarcode(barcodeInput.trim())" />
                <div v-if="barcodeError" role="alert" class="flex gap-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-red-500" fixed-width aria-hidden="true" />
                    <div>
                        <div class="font-semibold">{{ ctrans("The barcode was not saved") }}</div>
                        <div class="mt-1">{{ barcodeError }}</div>
                    </div>
                </div>
                <div class="flex justify-between gap-2">
                    <Button
                        v-if="barcodeHasNumber"
                        type="tertiary"
                        :label="ctrans('Remove barcode')"
                        :loading="isSavingBarcode"
                        @click="saveBarcode(null)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTrashAlt" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <div class="ml-auto flex w-full gap-2">
                        <Button type="tertiary" :label="ctrans('Cancel')" @click="isBarcodeModalOpen = false">
                            <template #icon>
                                <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button
                            type="tertiary"
                            :label="ctrans('Save')"
                            :loading="isSavingBarcode"
                            full
                            :disabled="!barcodeInput.trim()"
                            @click="saveBarcode(barcodeInput.trim())">
                            <template #icon>
                                <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
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

        <ProductImageViewerDialog
            v-if="viewerImages.length"
            v-model:visible="isImageViewerVisible"
            v-model:index="imageViewerIndex"
            :images="viewerImages" />
    </div>
</template>
