<script setup lang="ts">
import { computed, inject, ref, watch, type Ref } from "vue"
import axios from "axios"
import { set } from "lodash-es"
import { createReusableTemplate } from "@vueuse/core"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faCartPlus, faSyncAlt, faTrashAlt } from "@fal"
import { faCircle } from "@fas"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import LabelComingSoon from "@/Components/Iris/Products/LabelComingSoon.vue"
import Image from "@common/Components/Image.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { getBestOffer } from "@/Composables/useOffers"

library.add(faCheck, faCircle, faCartPlus, faSyncAlt, faTrashAlt)

interface SizeProduct {
    id: number
    code: string
    name?: string
    family_id?: number | null
    is_golden_product?: boolean
    variant_label?: string
    size_label?: string
    size_litres?: number | null
    price: number | string
    stock: number | string
    is_coming_soon?: boolean
    is_on_demand?: boolean
    pre_order?: any
    step_discount?: { label?: string, steps?: { min_quantity: number, percentage_off: number }[] } | null
    offers_data?: any
    currency_code?: string
    status_label?: string
    web_images?: { main?: { original?: any, thumbnail?: any } } | null
}

interface SavedLine {
    transaction_id: number | null
    quantity: number
}

const props = withDefaults(defineProps<{
    listProducts: SizeProduct[]
    axisLabel?: string
    productName?: string
    isLoggedIn?: boolean
    selectedProductId?: number | null
}>(), {
    axisLabel: "",
    productName: "",
    isLoggedIn: false,
    selectedProductId: null
})

const emits = defineEmits<{
    (e: "selectProduct", product: SizeProduct): void
}>()

const isViewing = (product: SizeProduct): boolean => props.selectedProductId === product.id

const viewSize = (product: SizeProduct) => {
    if (!isViewing(product)) {
        emits("selectProduct", product)
    }
}

const layout = inject<any>("layout", retinaLayoutStructure)
const customerDataByProduct = inject<any>("variantCustomerData", null)
const patchCustomerData = inject<((productId: number, patch: Record<string, any>) => void) | null>("patchVariantCustomerData", null)
const locale = useLocaleStore()

const [DefineQuantity, ReuseQuantity] = createReusableTemplate<{ product: SizeProduct }>()
const [DefineAvailability, ReuseAvailability] = createReusableTemplate<{ product: SizeProduct }>()
const [DefineLineTotal, ReuseLineTotal] = createReusableTemplate<{ product: SizeProduct }>()

const refreshCustomerData = inject<(() => Promise<void>) | null>("refreshVariantCustomerData", null)

const savedAmounts = ref<Record<number, { quantity: number, net: number, label: string | null }>>({})

const basketLine = (product: SizeProduct): SavedLine => {
    const data = customerDataByProduct?.value?.[product.id]

    return {
        transaction_id: data?.transaction_id ?? null,
        quantity: Number(data?.quantity_ordered_new ?? data?.quantity_ordered ?? 0) || 0
    }
}

const basketQuantity = (product: SizeProduct): number => basketLine(product).quantity

const drafts = inject<Ref<Record<number, number>>>("variantOrderDrafts", ref({}))
const limitHints = ref<Record<number, string>>({})
const rowMessages = ref<Record<number, string>>({})

const clearDraft = (productId: number) => {
    const { [productId]: _cleared, ...remaining } = drafts.value
    drafts.value = remaining
}

const price = (product: SizeProduct): number => Number(product.price) || 0

const isOrderable = (product: SizeProduct): boolean =>
    price(product) > 0
    && ((Number(product.stock) > 0 && !product.is_coming_soon) || Boolean(product.pre_order) || (Boolean(product.is_on_demand) && !product.is_coming_soon))

const stockLimit = (product: SizeProduct): number => {
    if (product.pre_order) {
        return product.pre_order.max_quantity ?? Infinity
    }

    return product.is_on_demand ? Infinity : (Number(product.stock) || 0)
}

const maxQuantity = (product: SizeProduct): number =>
    isOrderable(product) ? Math.max(stockLimit(product), basketQuantity(product)) : basketQuantity(product)

const rowCanChange = (product: SizeProduct): boolean => isOrderable(product) || basketQuantity(product) > 0

const showsLineTotal = (product: SizeProduct): boolean => rowCanChange(product) && selectedQuantity(product) > 0

const selectedQuantity = (product: SizeProduct): number => drafts.value[product.id] ?? basketQuantity(product)

const setQuantity = (product: SizeProduct, value: number | null) => {
    const quantity = Math.max(0, Math.round(Number(value) || 0))
    const max = maxQuantity(product)

    delete rowMessages.value[product.id]

    if (quantity > max) {
        limitHints.value[product.id] = ctrans("You cannot add more than :stock items.", { stock: String(max) })
        drafts.value = { ...drafts.value, [product.id]: max }
        return
    }

    delete limitHints.value[product.id]
    drafts.value = { ...drafts.value, [product.id]: quantity }
}

const isGoldRewardCustomer = computed<boolean>(() =>
    Boolean(layout?.user?.gr_data?.customer_is_gr || layout?.user?.gr_data?.amnesty)
)

const variantsInFamily = (familyId: number | null | undefined): SizeProduct[] =>
    props.listProducts.filter((product) => product.family_id === familyId)

const projectedFamilyQuantity = (familyId: number | null | undefined): number => {
    if (familyId == null) {
        return 0
    }

    const quantityInBasket = Number(layout?.family_quantity_ordered?.[familyId] ?? 0)
    const quantityChange = variantsInFamily(familyId).reduce(
        (total, product) => total + selectedQuantity(product) - basketQuantity(product),
        0
    )

    return quantityInBasket + quantityChange
}

const familyWillHaveGoldenProduct = (familyId: number | null | undefined): boolean => {
    if (familyId == null) {
        return false
    }

    return Boolean(layout?.family_has_golden_product?.[familyId])
        || variantsInFamily(familyId).some((product) => product.is_golden_product && selectedQuantity(product) > 0)
}

const offerPercentageOff = (product: SizeProduct): number => {
    const bestOffer = getBestOffer(product.offers_data)

    if (!bestOffer) {
        return 0
    }

    const percentageOff = Number(product.offers_data?.best_percentage_off?.percentage_off) || 0

    if (bestOffer.type !== "Category Quantity Ordered Order Interval") {
        return percentageOff
    }

    const trigger = bestOffer.category_qty_trigger
    const isMemberPriceActive = isGoldRewardCustomer.value
        || Boolean(product.is_golden_product)
        || familyWillHaveGoldenProduct(product.family_id)
        || (trigger != null && Number(trigger) <= projectedFamilyQuantity(product.family_id))

    return isMemberPriceActive ? percentageOff : 0
}

const stepSteps = (product: SizeProduct): { min_quantity: number, percentage_off: number }[] =>
    [...(product.step_discount?.steps ?? [])].sort((a, b) => a.min_quantity - b.min_quantity)

const stepPercentageOff = (product: SizeProduct, quantity: number): number =>
    stepSteps(product).reduce(
        (percentageOff: number, step) => (quantity >= step.min_quantity ? Number(step.percentage_off) || 0 : percentageOff),
        0
    )

const appliedDiscount = (product: SizeProduct): { percentageOff: number, source: "offer" | "step" | null } => {
    const offer = offerPercentageOff(product)
    const step = stepPercentageOff(product, selectedQuantity(product))

    if (offer <= 0 && step <= 0) {
        return { percentageOff: 0, source: null }
    }

    return offer >= step
        ? { percentageOff: offer, source: "offer" }
        : { percentageOff: step, source: "step" }
}

const roundMoney = (value: number): number => Math.round(value * 100) / 100

const percentageLabel = (percentageOff: number): string => `${Math.round(percentageOff * 1000) / 10}%`

const knownNetPerContainer = (product: SizeProduct): { net: number, label: string | null } | null => {
    const basket = basketQuantity(product)
    const saved = savedAmounts.value[product.id]

    if (saved && basket > 0 && saved.quantity === basket && Number.isFinite(saved.net) && saved.net > 0) {
        return { net: saved.net / saved.quantity, label: saved.label }
    }

    const data = customerDataByProduct?.value?.[product.id]
    const netPerQuantity = Number(data?.offer_net_amount_per_quantity)

    if (basket <= 0 || !Number.isFinite(netPerQuantity) || netPerQuantity <= 0) {
        return null
    }

    const offer = data?.offers_data?.o

    return { net: netPerQuantity, label: offer?.l ? `${offer.l} ${offer.p ?? ""}`.trim() : null }
}

const serverLine = (product: SizeProduct): { net: number, label: string | null } | null => {
    const quantity = selectedQuantity(product)
    const known = knownNetPerContainer(product)

    if (!known || quantity <= 0 || quantity !== basketQuantity(product)) {
        return null
    }

    return { net: known.net * quantity, label: known.label }
}

const effectivePercentage = (product: SizeProduct): number => {
    const known = knownNetPerContainer(product)
    const knownPercentage = known && price(product) > 0 ? Math.max(0, 1 - known.net / price(product)) : 0

    return Math.max(appliedDiscount(product).percentageOff, knownPercentage)
}

const containerPrice = (product: SizeProduct): number => {
    const server = serverLine(product)

    return server
        ? roundMoney(server.net / selectedQuantity(product))
        : roundMoney(price(product) * (1 - effectivePercentage(product)))
}

const lineTotal = (product: SizeProduct): number => {
    const server = serverLine(product)

    return server
        ? roundMoney(server.net)
        : roundMoney(selectedQuantity(product) * price(product) * (1 - effectivePercentage(product)))
}

const hasDiscountApplied = (product: SizeProduct): boolean =>
    serverLine(product) ? containerPrice(product) < price(product) : effectivePercentage(product) > 0

const pricePerLitre = (product: SizeProduct): number | null =>
    product.size_litres ? roundMoney(containerPrice(product) / Number(product.size_litres)) : null

const tierSteps = (product: SizeProduct) =>
    props.isLoggedIn ? stepSteps(product).filter((step) => Number(step.percentage_off) > 0) : []

const tierContainerPrice = (product: SizeProduct, percentageOff: number): number =>
    roundMoney(price(product) * (1 - percentageOff))

const appliedTierMinQuantity = (product: SizeProduct): number | null => {
    const reached = tierSteps(product).filter((step) => selectedQuantity(product) >= step.min_quantity)

    return reached.length ? reached[reached.length - 1].min_quantity : null
}

const isTierReached = (product: SizeProduct, step: { min_quantity: number }): boolean =>
    appliedTierMinQuantity(product) === step.min_quantity

const lineSaving = (product: SizeProduct): number =>
    showsLineTotal(product) ? Math.max(0, roundMoney(selectedQuantity(product) * price(product) - lineTotal(product))) : 0

const nextTier = (product: SizeProduct): { min_quantity: number, percentage_off: number } | null => {
    if (!rowCanChange(product) || !isOrderable(product)) {
        return null
    }

    const current = effectivePercentage(product)
    const quantity = selectedQuantity(product)

    return tierSteps(product).find((step) =>
        step.min_quantity > quantity
        && Number(step.percentage_off) > current
        && step.min_quantity <= maxQuantity(product)
    ) ?? null
}

const nextTierHint = (product: SizeProduct): string | null => {
    const step = nextTier(product)

    if (!step || selectedQuantity(product) <= 0) {
        return null
    }

    return ctrans("Add :quantity more for :price each", {
        quantity: String(step.min_quantity - selectedQuantity(product)),
        price: String(formatPrice(product, tierContainerPrice(product, Number(step.percentage_off))))
    })
}

const hasSizes = computed<boolean>(() => props.listProducts.some((product: any) => product.size_resolved))

const hasTiers = computed<boolean>(() => props.listProducts.some((product) => tierSteps(product).length > 0))

const discountLabels = computed<string>(() => [
    ...new Set(props.listProducts.map((product) => serverLine(product)?.label).filter(Boolean) as string[])
].join(", "))

const sizeOf = (product: SizeProduct): string => product.size_label || product.variant_label || product.code

const formatPrice = (product: SizeProduct, value: number) =>
    locale.currencyFormat(product.currency_code ?? layout?.iris?.currency?.code, value)

const stockLabel = (product: SizeProduct): string => {
    if (Number(product.stock) >= 250) {
        return ctrans("Unlimited quantity")
    }

    return ctrans(":stock items", { stock: String(Math.max(0, Number(product.stock) || 0)) })
}

const availabilityText = (product: SizeProduct): string => {
    if (Number(product.stock) > 0) {
        return stockLabel(product)
    }

    if (product.pre_order) {
        return `${product.pre_order.available_label} · ${product.pre_order.dispatch_label}`
    }

    return product.is_on_demand ? ctrans("Unlimited quantity") : ctrans("Out of stock")
}

const availabilityTone = (product: SizeProduct): string => {
    if (Number(product.stock) > 0 || product.is_on_demand) {
        return "text-green-600"
    }

    return product.pre_order ? "text-amber-500" : "text-red-600"
}

const changedProducts = computed<SizeProduct[]>(() =>
    props.listProducts.filter((product) => selectedQuantity(product) !== basketQuantity(product))
)

const changeSummary = computed<string>(() =>
    changedProducts.value
        .map((product) => {
            const difference = selectedQuantity(product) - basketQuantity(product)

            if (selectedQuantity(product) === 0) {
                return ctrans("remove :size", { size: sizeOf(product) })
            }

            return `${difference > 0 ? "+" : "−"}${Math.abs(difference)} × ${sizeOf(product)}`
        })
        .join(", ")
)

const saveButton = computed<{ label: string, icon: string, hint: string }>(() => {
    const changed = changedProducts.value

    if (!changed.length) {
        const hasBasketLines = props.listProducts.some((product) => basketQuantity(product) > 0)

        return hasBasketLines
            ? { label: ctrans("Your basket is up to date"), icon: "fal fa-check", hint: ctrans("Change a quantity above to update your basket.") }
            : { label: ctrans("Add to basket"), icon: "fal fa-cart-plus", hint: ctrans("Enter a quantity for one or more sizes to add them.") }
    }

    const adding = changed.filter((product) => basketQuantity(product) === 0)
    const removing = changed.filter((product) => selectedQuantity(product) === 0)

    if (adding.length === changed.length) {
        const containers = adding.reduce((total, product) => total + selectedQuantity(product), 0)

        return {
            label: adding.length > 1
                ? ctrans("Add :containers containers in :sizes sizes to basket", { containers: String(containers), sizes: String(adding.length) })
                : ctrans("Add :quantity × :size to basket", { quantity: String(containers), size: sizeOf(adding[0]) }),
            icon: "fal fa-cart-plus",
            hint: changeSummary.value,
        }
    }

    if (removing.length === changed.length) {
        return {
            label: removing.length > 1
                ? ctrans("Remove :sizes sizes from basket", { sizes: String(removing.length) })
                : ctrans("Remove :size from basket", { size: sizeOf(removing[0]) }),
            icon: "fal fa-trash-alt",
            hint: changeSummary.value,
        }
    }

    return {
        label: ctrans("Update basket (:count changes)", { count: String(changed.length) }),
        icon: "fal fa-sync-alt",
        hint: changeSummary.value,
    }
})

const grossTotal = computed<number>(() =>
    roundMoney(props.listProducts.reduce((total, product) => total + (rowCanChange(product) ? selectedQuantity(product) * price(product) : 0), 0))
)

const netTotal = computed<number>(() =>
    roundMoney(props.listProducts.reduce((total, product) => total + (rowCanChange(product) ? lineTotal(product) : 0), 0))
)

const totalSaving = computed<number>(() => roundMoney(grossTotal.value - netTotal.value))

const currencyCode = computed<string>(() => props.listProducts[0]?.currency_code ?? layout?.iris?.currency?.code)

const isSaving = ref(false)
const requestError = ref<string | null>(null)
const confirmation = ref<{ quantity: number, change: number, size: string }[]>([])

const updateBasketList = (product: SizeProduct, line: any) => {
    const currentList = layout?.family_page?.productInBasket?.list || {}

    set(layout, ["family_page", "productInBasket", "list"], {
        ...currentList,
        [product.id]: {
            ...(currentList[product.id] ?? {}),
            transaction_id: line.transaction_id,
            quantity_ordered: line.quantity_ordered,
            quantity_ordered_new: line.quantity_ordered,
            family_id: line.family_id,
            is_golden_product: line.is_golden_product,
        }
    })
}

const applyBasketListChange = (list: Record<string, any>, previous: Record<string, any> | undefined) => {
    props.listProducts.forEach((product) => {
        const entry = list?.[product.id]
        const hadEntry = Boolean(previous?.[product.id])

        if (!entry && !hadEntry) {
            return
        }

        const entryQuantity = Number(entry?.quantity_ordered_new ?? entry?.quantity_ordered ?? 0) || 0

        if (entryQuantity === basketQuantity(product)) {
            return
        }

        const { [product.id]: _dropped, ...remaining } = savedAmounts.value
        savedAmounts.value = remaining
        clearDraft(product.id)
        patchCustomerData?.(product.id, {
            quantity_ordered: entryQuantity || null,
            quantity_ordered_new: entryQuantity || null,
            transaction_id: entryQuantity ? (entry?.transaction_id ?? null) : null
        })
    })
}

watch(
    () => layout?.family_page?.productInBasket?.list,
    (list, previous) => applyBasketListChange(list ?? {}, previous),
    { deep: true }
)

const onSave = async () => {
    if (!changedProducts.value.length || isSaving.value) {
        return
    }

    isSaving.value = true
    requestError.value = null
    confirmation.value = []
    rowMessages.value = {}

    try {
        const response = await axios.post(route("iris.models.transactions.store"), {
            lines: changedProducts.value.map((product) => ({ product_id: product.id, quantity: selectedQuantity(product) }))
        })

        const added: { quantity: number, change: number, size: string }[] = []
        const previousQuantities = Object.fromEntries(changedProducts.value.map((product) => [product.id, basketQuantity(product)]))

        response.data.lines.forEach((line: any) => {
            const product = props.listProducts.find((item) => item.id === line.product_id)

            if (!product) {
                return
            }

            if (line.status === "failed") {
                rowMessages.value[product.id] = line.message || ctrans("Failed to update product quantity")
                return
            }

            clearDraft(product.id)
            savedAmounts.value = {
                ...savedAmounts.value,
                [product.id]: { quantity: line.quantity_ordered, net: Number(line.net_amount), label: line.offer_label ?? null }
            }
            updateBasketList(product, line)
            patchCustomerData?.(product.id, {
                transaction_id: line.transaction_id,
                quantity_ordered: line.quantity_ordered,
                quantity_ordered_new: line.quantity_ordered
            })

            if (line.status === "added" || line.status === "updated") {
                added.push({
                    quantity: line.quantity_ordered,
                    change: line.quantity_ordered - (previousQuantities[product.id] ?? 0),
                    size: sizeOf(product)
                })
            }
        })

        const keptIds = new Set(response.data.lines.filter((line: any) => line.status === "added" || line.status === "updated").map((line: any) => line.product_id))

        savedAmounts.value = Object.fromEntries(Object.entries(savedAmounts.value).filter(([id]) => keptIds.has(Number(id))))

        confirmation.value = added
        layout?.reload_handle?.()
        await refreshCustomerData?.()
    } catch (error: any) {
        requestError.value = error?.response?.data?.message || ctrans("Failed to update product quantity")
        layout?.reload_handle?.()
        await refreshCustomerData?.()
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="variant-size-ordering mt-5">
        <DefineQuantity v-slot="{ product }">
            <div v-if="rowCanChange(product)" class="flex flex-col items-start gap-1">
                <InputNumber :modelValue="selectedQuantity(product)" :min="0" :max="maxQuantity(product) === Infinity ? undefined : maxQuantity(product)"
                    :useGrouping="false" :maxFractionDigits="0" showButtons buttonLayout="horizontal" :disabled="isSaving"
                    :inputId="`size-qty-${product.id}`" :ariaLabel="ctrans('Quantity of :size', { size: sizeOf(product) })"
                    :inputStyle="{ width: '2.75rem', textAlign: 'center', padding: '0.25rem' }" class="sizes-quantity"
                    @update:modelValue="(value: number | null) => setQuantity(product, value)">
                    <template #incrementbuttonicon><span aria-hidden="true">+</span></template>
                    <template #decrementbuttonicon><span aria-hidden="true">−</span></template>
                </InputNumber>
                <span v-if="limitHints[product.id]" class="text-xs text-amber-600">{{ limitHints[product.id] }}</span>
            </div>
            <span v-else class="text-sm text-gray-500">{{ ctrans("Not available") }}</span>
        </DefineQuantity>

        <DefineAvailability v-slot="{ product }">
            <LabelComingSoon v-if="product.is_coming_soon && !product.pre_order" :product="product" />
            <div v-else class="flex items-center gap-2 text-sm text-gray-600">
                <FontAwesomeIcon icon="fas fa-circle" class="shrink-0 text-[8px]" :class="availabilityTone(product)" fixed-width aria-hidden="true" />
                <span :class="{ 'text-red-600': availabilityTone(product) === 'text-red-600' }">{{ availabilityText(product) }}</span>
            </div>
        </DefineAvailability>

        <DefineLineTotal v-slot="{ product }">
            <template v-if="showsLineTotal(product)">
                <div class="font-semibold text-gray-900">{{ formatPrice(product, lineTotal(product)) }}</div>
                <div v-if="lineSaving(product) > 0" class="text-xs font-medium text-green-600">
                    {{ ctrans("You save :amount", { amount: String(formatPrice(product, lineSaving(product))) }) }}
                </div>
            </template>
            <span v-else class="text-gray-400">—</span>
            <div v-if="nextTierHint(product)" class="mt-0.5 text-xs text-gray-500">{{ nextTierHint(product) }}</div>
        </DefineLineTotal>

        <div class="step-header mb-2 flex items-center gap-3">
            <span class="header-line" />
            <span class="header-text">{{ isLoggedIn && hasTiers ? ctrans("Buy more, save more") : (hasSizes ? ctrans("Choose sizes") : (axisLabel || ctrans("Size"))) }}</span>
            <span class="header-line" />
        </div>
        <p v-if="hasSizes" class="mb-3 text-xs text-gray-500">
            {{ ctrans("Size is the amount in each container. Quantity is the number of containers you receive: 5 × 1L is five 1L containers, not one 5L container.") }}
        </p>

        <template v-if="isLoggedIn">
            <div class="flex flex-col gap-3">
                <div v-for="product in listProducts" :key="product.id" class="size-card rounded-xl border px-4 py-3"
                    :class="{ 'is-active': selectedQuantity(product) > 0, 'is-viewing': isViewing(product), 'is-disabled': !rowCanChange(product) }"
                    @click="viewSize(product)">
                    <div class="flex items-start justify-between gap-3">
                        <button type="button" class="flex min-w-0 items-start gap-3 text-left focus:outline-none"
                            :aria-pressed="isViewing(product)"
                            :aria-label="ctrans('Show photos and details of :size', { size: sizeOf(product) })"
                            @click.stop="viewSize(product)">
                            <span class="size-thumb">
                                <Image v-if="product.web_images?.main?.thumbnail || product.web_images?.main?.original"
                                    :src="product.web_images?.main?.thumbnail ?? product.web_images?.main?.original" :alt="sizeOf(product)"
                                    class="h-full w-full object-contain" />
                            </span>
                            <span class="min-w-0">
                            <span class="block text-base font-bold text-gray-900">{{ sizeOf(product) }}</span>
                            <span v-if="isViewing(product)" class="viewing-label">{{ ctrans("Shown above") }}</span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                                <span class="whitespace-nowrap">{{ product.code }}</span>
                                <ReuseAvailability :product="product" />
                            </span>
                            </span>
                        </button>
                        <div class="shrink-0 text-right">
                            <template v-if="price(product) > 0">
                                <div class="text-base font-bold text-gray-900">
                                    {{ formatPrice(product, containerPrice(product)) }}
                                    <span class="text-xs font-normal text-gray-500">{{ ctrans("each") }}</span>
                                </div>
                                <div v-if="hasDiscountApplied(product)" class="text-sm text-gray-400 line-through">{{ formatPrice(product, price(product)) }}</div>
                                <div v-if="pricePerLitre(product) !== null" class="text-xs text-gray-500">
                                    {{ ctrans(":price per litre", { price: String(formatPrice(product, pricePerLitre(product) as number)) }) }}
                                </div>
                            </template>
                            <span v-else class="text-sm text-gray-500">{{ ctrans("Price not available") }}</span>
                        </div>
                    </div>

                    <div v-if="tierSteps(product).length && rowCanChange(product)" class="mt-2 flex flex-wrap gap-1.5" @click.stop
                        :aria-label="ctrans('Volume prices for :size', { size: sizeOf(product) })">
                        <button v-for="step in tierSteps(product)" :key="step.min_quantity" type="button" class="tier-chip"
                            :class="{ 'is-reached': isTierReached(product, step) }" :disabled="isSaving"
                            @click="setQuantity(product, step.min_quantity)">
                            <span class="font-bold">{{ step.min_quantity }}+</span>
                            {{ formatPrice(product, tierContainerPrice(product, Number(step.percentage_off))) }}
                            <span class="save-badge">{{ ctrans("Save :percentage", { percentage: percentageLabel(Number(step.percentage_off)) }) }}</span>
                        </button>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3">
                        <div @click.stop><ReuseQuantity :product="product" /></div>
                        <div class="text-right tabular-nums"><ReuseLineTotal :product="product" /></div>
                    </div>
                    <div v-if="rowMessages[product.id]" class="mt-2 text-xs text-red-600">{{ rowMessages[product.id] }}</div>
                </div>
            </div>

            <p v-if="hasTiers" class="mt-3 text-xs text-gray-500">
                {{ ctrans("Volume prices apply automatically and count the containers of one size in your basket; different sizes are not added together.") }}
            </p>
            <p v-if="discountLabels" class="mt-1 text-xs text-gray-500">
                {{ ctrans("Your discount: :label", { label: discountLabels }) }}
            </p>

            <div class="mt-4 flex flex-col gap-2 border-t border-gray-300 pt-3">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium">{{ ctrans("Total excl. VAT") }}</span>
                    <span class="font-semibold tabular-nums">{{ locale.currencyFormat(currencyCode, netTotal) }}</span>
                </div>
                <div v-if="totalSaving > 0" class="text-right text-sm font-medium text-green-600">
                    {{ ctrans("You save :amount", { amount: String(locale.currencyFormat(currencyCode, totalSaving)) }) }}
                </div>

                <p class="text-xs text-gray-500">{{ ctrans("Discounts that depend on the whole order are applied when the items are in the basket.") }}</p>

                <Button :type="changedProducts.length ? 'primary' : 'tertiary'" size="lg" full :loading="isSaving"
                    :disabled="!changedProducts.length || isSaving" class="save-button" :class="{ 'is-idle': !changedProducts.length }" @click="onSave">
                    <template #loading><LoadingIcon v-if="isSaving" /></template>
                    <template #icon>
                        <FontAwesomeIcon :icon="saveButton.icon" fixed-width aria-hidden="true" />
                    </template>
                    <template #label>{{ saveButton.label }}</template>
                </Button>
                <p class="text-center text-xs" :class="changedProducts.length ? 'font-medium text-gray-700' : 'text-gray-500'">
                    <template v-if="changedProducts.length">{{ ctrans("Not saved yet:") }} </template>{{ saveButton.hint }}
                </p>

                <div v-if="requestError" class="text-xs text-red-600">{{ requestError }}</div>

                <div v-if="confirmation.length" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800" role="status">
                    <div class="font-semibold">{{ ctrans("Basket updated") }}: {{ productName }}</div>
                    <ul class="mt-1 space-y-0.5">
                        <li v-for="line in confirmation" :key="line.size">
                            <template v-if="line.change > 0 && line.change !== line.quantity">
                                {{ ctrans(":change × :size added, :quantity in your basket", { change: String(line.change), size: line.size, quantity: String(line.quantity) }) }}
                            </template>
                            <template v-else-if="line.change > 0">
                                {{ ctrans(":quantity × :size added", { quantity: String(line.quantity), size: line.size }) }}
                            </template>
                            <template v-else>
                                {{ ctrans(":size changed to :quantity in your basket", { quantity: String(line.quantity), size: line.size }) }}
                            </template>
                        </li>
                    </ul>
                </div>
            </div>
        </template>

        <template v-else>
            <ul class="divide-y divide-gray-200 border-y border-gray-200">
                <li v-for="product in listProducts" :key="product.id" class="flex items-baseline justify-between gap-3 py-2">
                    <span class="font-semibold text-gray-900">{{ sizeOf(product) }}</span>
                    <span class="whitespace-nowrap text-xs text-gray-400">{{ product.code }}</span>
                </li>
            </ul>
            <LinkIris :href="urlLoginWithRedirect()" class="mt-3 block w-full rounded border px-3 py-2 text-center text-sm text-gray-600">
                {{ ctrans("Login or Register for Wholesale Prices") }}
            </LinkIris>
        </template>
    </div>
</template>

<style scoped>
.variant-size-ordering {
    --step-color: #c48497;
}

.header-line {
    flex: 1;
    height: 1px;
    background: color-mix(in srgb, var(--step-color) 30%, #d1d5db);
}

.header-text {
    white-space: nowrap;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--step-color);
}

.size-card {
    cursor: pointer;
    border-color: color-mix(in srgb, var(--step-color) 25%, white);
    background: color-mix(in srgb, var(--step-color) 4%, white);
    transition: border-color 0.2s ease, background 0.2s ease;
}

.size-card:hover {
    border-color: color-mix(in srgb, var(--step-color) 60%, white);
}

.size-card.is-active {
    border-width: 2px;
    border-color: var(--step-color);
    background: white;
}

.size-card.is-viewing {
    border-width: 2px;
    border-color: var(--step-color);
    background: color-mix(in srgb, var(--step-color) 10%, white);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--step-color) 18%, transparent);
    cursor: default;
}

.size-thumb {
    display: block;
    flex-shrink: 0;
    width: 3rem;
    height: 3rem;
    overflow: hidden;
    border-radius: 0.5rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
}

.viewing-label {
    display: inline-block;
    margin-top: 2px;
    padding: 0 8px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: white;
    background: var(--step-color);
    border-radius: 9999px;
}

.size-card.is-disabled {
    opacity: 0.6;
}

.tier-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 4px 3px 10px;
    font-size: 12px;
    color: #374151;
    background: white;
    border: 1px solid color-mix(in srgb, var(--step-color) 30%, #e5e7eb);
    border-radius: 9999px;
    transition: border-color 0.2s ease;
}

.tier-chip:hover:not(:disabled) {
    border-color: var(--step-color);
}

.tier-chip.is-reached {
    border-color: var(--step-color);
    background: color-mix(in srgb, var(--step-color) 10%, white);
}

.save-badge {
    padding: 1px 8px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--step-color);
    background: color-mix(in srgb, var(--step-color) 12%, white);
    border-radius: 9999px;
}

.tier-chip.is-reached .save-badge {
    color: white;
    background: var(--step-color);
}

.save-button.is-idle {
    opacity: 0.55;
    cursor: not-allowed;
}

.sizes-quantity :deep(.p-inputnumber-button) {
    width: 2rem;
}
</style>
