<script setup lang="ts">
import { computed, inject, ref, type Ref } from "vue"
import axios from "axios"
import { set } from "lodash-es"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCartPlus, faCheckCircle, faExclamationTriangle } from "@fal"
import { faFire, faCircle, faPlus, faMinus } from "@fas"

import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"

library.add(faCartPlus, faCheckCircle, faExclamationTriangle, faFire, faCircle, faPlus, faMinus)

interface Step {
    min_quantity: number
    percentage_off: number
    is_popular?: boolean
}

interface SizeProduct {
    id: number
    code: string
    name?: string
    price: number | string
    stock: number | string | null
    currency_code?: string
    variant_label?: string | null
    size_label?: string | null
    size_litres?: number | null
    is_sample?: boolean
    is_coming_soon?: boolean
    is_on_demand?: boolean
    pre_order?: { max_quantity?: number | null, available_label?: string, dispatch_label?: string } | null
    step_discount?: { label?: string | null, steps?: Step[] } | null
    family_id?: number | null
    is_golden_product?: boolean
}

interface Confirmation {
    size: string
    change: number
    quantity: number
}

const props = withDefaults(defineProps<{
    product: SizeProduct
    listProducts: SizeProduct[]
    isLoggedIn?: boolean
    buttonStyle?: object | null
}>(), {
    isLoggedIn: false,
    buttonStyle: null,
})

const emits = defineEmits<{
    (e: "selectProduct", product: SizeProduct): void
}>()

const layout = inject<any>("layout", retinaLayoutStructure)
const customerDataByProduct = inject<Ref<Record<number, any>> | null>("variantCustomerData", null)
const patchCustomerData = inject<((productId: number, patch: Record<string, any>) => void) | null>("patchVariantCustomerData", null)
const refreshCustomerData = inject<(() => Promise<void>) | null>("refreshVariantCustomerData", null)
const drafts = inject<Ref<Record<number, number>>>("variantOrderDrafts", ref({}))
const locale = useLocaleStore()

const isSaving = ref(false)
const requestError = ref<string | null>(null)
const rowMessages = ref<Record<number, string>>({})
const confirmation = ref<Confirmation[]>([])

const sizes = computed<SizeProduct[]>(() =>
    props.listProducts.map((item) => (item.id === props.product.id ? { ...item, ...props.product, size_label: item.size_label, variant_label: item.variant_label } : item))
)

const selected = computed<SizeProduct>(() => sizes.value.find((item) => item.id === props.product.id) ?? props.product)

const roundMoney = (value: number): number => Math.round(value * 100) / 100
const price = (item: SizeProduct): number => Number(item.price) || 0
const currencyCode = (item: SizeProduct): string => item.currency_code ?? layout?.iris?.currency?.code
const money = (item: SizeProduct, amount: number) => locale.currencyFormat(currencyCode(item), amount)
const percentageLabel = (percentageOff: number): string => `${Math.round(percentageOff * 1000) / 10}%`

const sizeOf = (item: SizeProduct): string => item.size_label || item.variant_label || item.code

const customerData = (item: SizeProduct) => customerDataByProduct?.value?.[item.id]

const basketQuantity = (item: SizeProduct): number =>
    Math.max(0, Math.round(Number(customerData(item)?.quantity_ordered ?? 0) || 0))

const quantityOf = (item: SizeProduct): number => drafts.value[item.id] ?? basketQuantity(item)

const isChanged = (item: SizeProduct): boolean => quantityOf(item) !== basketQuantity(item)

const stockOf = (item: SizeProduct): number => Math.max(0, Number(customerData(item)?.stock ?? item.stock) || 0)

const isOrderable = (item: SizeProduct): boolean =>
    price(item) > 0
    && ((stockOf(item) > 0 && !item.is_coming_soon) || Boolean(item.pre_order) || (Boolean(item.is_on_demand) && !item.is_coming_soon))

const maxQuantity = (item: SizeProduct): number | undefined => {
    if (!isOrderable(item)) {
        return basketQuantity(item)
    }

    if (item.pre_order) {
        return item.pre_order.max_quantity ? Math.max(item.pre_order.max_quantity, basketQuantity(item)) : undefined
    }

    return item.is_on_demand ? undefined : Math.max(stockOf(item), basketQuantity(item))
}

const setQuantity = (item: SizeProduct, value: number | null) => {
    const max = maxQuantity(item)
    const next = Math.max(0, Math.round(Number(value) || 0))

    delete rowMessages.value[item.id]
    confirmation.value = []
    drafts.value = { ...drafts.value, [item.id]: max === undefined ? next : Math.min(next, max) }
}

const steps = (item: SizeProduct): Step[] =>
    [...(item.step_discount?.steps ?? [])]
        .filter((step) => Number(step.percentage_off) > 0)
        .sort((a, b) => a.min_quantity - b.min_quantity)

const stepPercentage = (item: SizeProduct, quantity: number): number =>
    steps(item).reduce((current, step) => (quantity >= step.min_quantity ? Number(step.percentage_off) || 0 : current), 0)

const stepPrice = (item: SizeProduct, percentageOff: number): number => roundMoney(price(item) * (1 - percentageOff))

const tierCards = (item: SizeProduct): Step[] => {
    const itemSteps = steps(item)

    if (!itemSteps.length) {
        return []
    }

    return itemSteps[0].min_quantity > 1 ? [{ min_quantity: 1, percentage_off: 0 }, ...itemSteps] : itemSteps
}

const tierTitle = (item: SizeProduct, step: Step): string => {
    if (step.percentage_off > 0) {
        return ctrans(":quantity+ × :size", { quantity: String(step.min_quantity), size: sizeOf(item) })
    }

    const upTo = steps(item)[0].min_quantity - 1

    return upTo > 1
        ? ctrans("1–:quantity × :size", { quantity: String(upTo), size: sizeOf(item) })
        : ctrans("1 × :size", { size: sizeOf(item) })
}

const isActiveTier = (item: SizeProduct, step: Step): boolean =>
    quantityOf(item) > 0 && (reachedStep(item) ?? 1) === step.min_quantity

const popularMinQuantity = (item: SizeProduct): number | null => steps(item).find((step) => step.is_popular)?.min_quantity ?? null

const basketOffer = (item: SizeProduct): { label: string, isVolumePrice: boolean } | null => {
    const offer = customerData(item)?.offers_data?.o

    if (!offer?.l || basketQuantity(item) <= 0) {
        return null
    }

    return {
        label: `${offer.l} ${offer.p ?? ""}`.trim(),
        isVolumePrice: offer.st === "sd",
    }
}

const basketNetPerContainer = (item: SizeProduct): number | null => {
    const net = Number(customerData(item)?.offer_net_amount_per_quantity)

    return basketQuantity(item) > 0 && Number.isFinite(net) && net > 0 ? net : null
}

const accountPercentage = (item: SizeProduct): number => {
    const offer = basketOffer(item)
    const net = basketNetPerContainer(item)

    if (!offer || offer.isVolumePrice || !net || price(item) <= 0) {
        return 0
    }

    return Math.max(0, 1 - net / price(item))
}

const isServerPrice = (item: SizeProduct): boolean => !isChanged(item) && basketNetPerContainer(item) !== null

const containerPrice = (item: SizeProduct): number => {
    const net = basketNetPerContainer(item)

    if (isServerPrice(item) && net !== null) {
        return roundMoney(net)
    }

    return stepPrice(item, Math.max(stepPercentage(item, quantityOf(item)), accountPercentage(item)))
}

const lineTotal = (item: SizeProduct): number => roundMoney(containerPrice(item) * quantityOf(item))

const lineSaving = (item: SizeProduct): number => Math.max(0, roundMoney(price(item) * quantityOf(item) - lineTotal(item)))

const pricePerLitre = (item: SizeProduct, amount: number): number | null =>
    item.size_litres ? roundMoney(amount / Number(item.size_litres)) : null

const reachedStep = (item: SizeProduct): number | null => {
    const quantity = quantityOf(item)
    const reached = steps(item).filter((step) => quantity >= step.min_quantity)

    return reached.length ? reached[reached.length - 1].min_quantity : null
}

const nextStep = (item: SizeProduct): Step | null => {
    const quantity = quantityOf(item)
    const max = maxQuantity(item)

    return steps(item).find((step) =>
        step.min_quantity > quantity
        && Number(step.percentage_off) > Math.max(stepPercentage(item, quantity), accountPercentage(item))
        && (max === undefined || step.min_quantity <= max)
    ) ?? null
}

const availabilityLabel = (item: SizeProduct): string => {
    if (price(item) <= 0) {
        return ctrans("Price not available")
    }

    if (stockOf(item) > 0 && !item.is_coming_soon) {
        return stockOf(item) >= 250 ? ctrans("In stock") : ctrans(":stock in stock", { stock: String(stockOf(item)) })
    }

    if (item.pre_order) {
        return [item.pre_order.available_label, item.pre_order.dispatch_label].filter(Boolean).join(" · ")
    }

    if (item.is_on_demand && !item.is_coming_soon) {
        return ctrans("Made to order")
    }

    return item.is_coming_soon ? ctrans("Coming soon") : ctrans("Out of stock")
}

const availabilityTone = (item: SizeProduct): string => {
    if (!isOrderable(item)) {
        return "text-red-600"
    }

    return stockOf(item) > 0 || item.is_on_demand ? "text-green-600" : "text-amber-500"
}

const containerWord = (quantity: number): string => (quantity === 1 ? ctrans("container") : ctrans("containers"))

const changedSizes = computed<SizeProduct[]>(() => sizes.value.filter((item) => isChanged(item)))

const basketSizes = computed<SizeProduct[]>(() => sizes.value.filter((item) => basketQuantity(item) > 0 || quantityOf(item) > 0))

const selectionTotal = computed<number>(() => roundMoney(basketSizes.value.reduce((total, item) => total + lineTotal(item), 0)))

const selectionSaving = computed<number>(() => roundMoney(basketSizes.value.reduce((total, item) => total + lineSaving(item), 0)))

const accountOfferLabels = computed<string[]>(() => [
    ...new Set(sizes.value.map((item) => basketOffer(item)).filter((offer) => offer && !offer.isVolumePrice).map((offer) => offer!.label)),
])

const buttonLabel = computed<string>(() => {
    if (changedSizes.value.length > 1) {
        return ctrans("Add selected items to basket (:count sizes)", { count: String(changedSizes.value.length) })
    }

    return changedSizes.value.every((item) => basketQuantity(item) > 0) && changedSizes.value.length
        ? ctrans("Update basket")
        : ctrans("Add to basket")
})

const updateLayoutBasket = (item: SizeProduct, line: any) => {
    const currentList = layout?.family_page?.productInBasket?.list || {}

    set(layout, ["family_page", "productInBasket", "list"], {
        ...currentList,
        [item.id]: {
            ...(currentList[item.id] ?? {}),
            transaction_id: line.transaction_id,
            quantity_ordered: line.quantity_ordered,
            quantity_ordered_new: line.quantity_ordered,
            family_id: line.family_id,
            is_golden_product: line.is_golden_product,
        },
    })
}

const onSave = async () => {
    if (!changedSizes.value.length || isSaving.value) {
        return
    }

    isSaving.value = true
    requestError.value = null
    rowMessages.value = {}
    confirmation.value = []

    const previous = Object.fromEntries(changedSizes.value.map((item) => [item.id, basketQuantity(item)]))

    try {
        const response = await axios.post(route("iris.models.transactions.store"), {
            lines: changedSizes.value.map((item) => ({ product_id: item.id, quantity: quantityOf(item) })),
        })

        const done: Confirmation[] = []
        const nextDrafts = { ...drafts.value }

        response.data.lines.forEach((line: any) => {
            const item = sizes.value.find((size) => size.id === line.product_id)

            if (!item) {
                return
            }

            if (line.status === "failed") {
                rowMessages.value[item.id] = line.message || ctrans("This product is not available for purchase.")
                return
            }

            delete nextDrafts[item.id]
            updateLayoutBasket(item, line)
            patchCustomerData?.(item.id, {
                transaction_id: line.transaction_id,
                quantity_ordered: line.quantity_ordered || null,
                quantity_ordered_new: line.quantity_ordered || null,
            })
            done.push({ size: sizeOf(item), change: line.quantity_ordered - (previous[item.id] ?? 0), quantity: line.quantity_ordered })
        })

        drafts.value = nextDrafts
        confirmation.value = done
        layout?.reload_handle?.()
        await refreshCustomerData?.()
    } catch (error: any) {
        requestError.value = error?.response?.data?.message || ctrans("Nothing was added to your basket. Please try again.")
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="variant-step-ordering mt-4">
        <!-- 1. Choose the size: changes images, code, price and description of the page -->
        <div class="mb-1 flex items-baseline justify-between gap-2">
            <span class="text-sm font-semibold text-gray-800">{{ ctrans("Size") }}</span>
            <span class="text-xs text-gray-500">{{ ctrans("The amount in each container") }}</span>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" :aria-label="ctrans('Size')">
            <button v-for="item in sizes" :key="item.id" type="button" role="radio"
                :aria-checked="item.id === selected.id"
                class="size-pill relative rounded-lg border px-3 py-2 text-left transition"
                :class="[item.id === selected.id ? 'is-selected' : 'border-gray-200 hover:border-gray-400', { 'opacity-60': !isOrderable(item) }]"
                @click="item.id !== selected.id && emits('selectProduct', item)">
                <div class="font-semibold text-gray-900">{{ sizeOf(item) }}</div>
                <div v-if="isLoggedIn && price(item) > 0" class="text-xs text-gray-600">{{ money(item, price(item)) }}</div>
                <div class="text-[11px]" :class="availabilityTone(item)">{{ availabilityLabel(item) }}</div>
                <span v-if="isLoggedIn && quantityOf(item) > 0" class="qty-badge">
                    {{ isChanged(item) ? ctrans(":quantity selected", { quantity: String(quantityOf(item)) }) : ctrans(":quantity in basket", { quantity: String(quantityOf(item)) }) }}
                </span>
            </button>
        </div>

        <template v-if="isLoggedIn">
            <!-- 2. Volume prices of the chosen size, same look as the bulk offer -->
            <div v-if="isOrderable(selected)" class="mt-5">
                <div class="step-header mb-3 flex items-center gap-3">
                    <span class="header-line" />
                    <span class="header-text">{{ steps(selected).length ? ctrans("Bulk offer") : ctrans("Quantity") }}</span>
                    <span class="header-line" />
                </div>

                <div v-if="steps(selected).length" class="flex flex-col gap-2.5">
                    <button v-for="step in tierCards(selected)" :key="step.min_quantity" type="button"
                        class="step-tier relative flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left"
                        :class="{ 'is-active': isActiveTier(selected, step), 'is-popular': popularMinQuantity(selected) === step.min_quantity }"
                        :aria-pressed="isActiveTier(selected, step)"
                        :disabled="isSaving || (maxQuantity(selected) !== undefined && step.min_quantity > (maxQuantity(selected) as number))"
                        @click="setQuantity(selected, step.min_quantity)">
                        <span v-if="popularMinQuantity(selected) === step.min_quantity" class="popular-badge">
                            <FontAwesomeIcon :icon="faFire" fixed-width /> {{ ctrans("Popular") }}
                        </span>
                        <span class="radio" :class="{ 'radio-checked': isActiveTier(selected, step) }"><span class="radio-dot" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-gray-900">
                                {{ tierTitle(selected, step) }}
                            </div>
                            <div class="text-sm text-gray-500">
                                <template v-if="step.percentage_off > 0">
                                    {{ ctrans("Save :amount per container (:percentage)", { amount: String(money(selected, price(selected) - stepPrice(selected, step.percentage_off))), percentage: percentageLabel(step.percentage_off) }) }}
                                </template>
                                <template v-else>{{ ctrans("Standard price") }}</template>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-bold text-gray-900">{{ money(selected, stepPrice(selected, step.percentage_off)) }} <span class="text-xs font-normal text-gray-500">{{ ctrans("each") }}</span></div>
                            <div v-if="step.percentage_off > 0" class="text-sm text-gray-400 line-through">{{ money(selected, price(selected)) }}</div>
                            <div v-if="pricePerLitre(selected, stepPrice(selected, step.percentage_off))" class="text-xs text-gray-500">
                                {{ ctrans(":price per litre", { price: String(money(selected, pricePerLitre(selected, stepPrice(selected, step.percentage_off))!)) }) }}
                            </div>
                        </div>
                    </button>
                    <p class="text-xs text-gray-500">
                        {{ ctrans("Volume prices apply automatically as you change the quantity. They count containers of this size only; other sizes do not add up.") }}
                    </p>
                </div>

                <!-- 3. Quantity of the chosen size and its line -->
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <label :for="`step-qty-${selected.id}`" class="mb-1 block text-xs text-gray-500">
                            {{ ctrans("Quantity (number of :size containers)", { size: sizeOf(selected) }) }}
                        </label>
                        <InputNumber :modelValue="quantityOf(selected)" :min="0" :max="maxQuantity(selected)" :useGrouping="false" :maxFractionDigits="0"
                            showButtons buttonLayout="horizontal" :disabled="isSaving" :inputId="`step-qty-${selected.id}`"
                            :inputStyle="{ width: '3rem', textAlign: 'center' }" class="step-quantity"
                            @update:modelValue="(value: number | null) => setQuantity(selected, value)">
                            <template #incrementbuttonicon><FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" /></template>
                            <template #decrementbuttonicon><FontAwesomeIcon :icon="faMinus" fixed-width aria-hidden="true" /></template>
                        </InputNumber>
                    </div>
                    <div v-if="quantityOf(selected) > 0" class="text-right">
                        <div class="text-xs text-gray-500">
                            {{ quantityOf(selected) }} × {{ sizeOf(selected) }} · {{ money(selected, containerPrice(selected)) }} {{ ctrans("each") }}
                        </div>
                        <div class="text-lg font-bold text-gray-900">{{ money(selected, lineTotal(selected)) }}</div>
                        <div v-if="lineSaving(selected) > 0" class="text-xs font-medium text-green-600">
                            {{ ctrans("You save :amount", { amount: String(money(selected, lineSaving(selected))) }) }}
                        </div>
                    </div>
                </div>
                <div v-if="nextStep(selected) && quantityOf(selected) > 0" class="mt-2 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-700">
                    {{ ctrans("Add :quantity more :containers to pay :price each", {
                        quantity: String(nextStep(selected)!.min_quantity - quantityOf(selected)),
                        containers: containerWord(nextStep(selected)!.min_quantity - quantityOf(selected)),
                        price: String(money(selected, stepPrice(selected, nextStep(selected)!.percentage_off))),
                    }) }}
                </div>
                <div v-if="rowMessages[selected.id]" class="mt-2 text-xs text-red-600">{{ rowMessages[selected.id] }}</div>
            </div>
            <div v-else class="mt-4 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600">
                {{ ctrans(":size is not available to order: :reason", { size: sizeOf(selected), reason: availabilityLabel(selected) }) }}
            </div>

            <!-- 4. Everything of this product in the basket or about to be, across sizes -->
            <div v-if="basketSizes.length" class="mt-5 rounded-lg border border-gray-200">
                <div class="border-b border-gray-200 px-3 py-2 text-sm font-semibold text-gray-800">{{ ctrans("Your order for this product") }}</div>
                <ul class="divide-y divide-gray-100 text-sm">
                    <li v-for="item in basketSizes" :key="item.id" class="flex items-start justify-between gap-3 px-3 py-2">
                        <div>
                            <button type="button" class="font-medium text-gray-900 underline-offset-2 hover:underline" @click="item.id !== selected.id && emits('selectProduct', item)">
                                {{ quantityOf(item) }} × {{ sizeOf(item) }}
                            </button>
                            <div class="text-xs" :class="isChanged(item) ? 'text-amber-600' : 'text-gray-500'">
                                <template v-if="isChanged(item) && basketQuantity(item) > 0">{{ ctrans("Not saved yet (basket has :quantity)", { quantity: String(basketQuantity(item)) }) }}</template>
                                <template v-else-if="isChanged(item)">{{ ctrans("Not added yet") }}</template>
                                <template v-else>{{ ctrans("In your basket") }}<span v-if="basketOffer(item)"> · {{ basketOffer(item)!.label }}</span></template>
                            </div>
                            <div v-if="rowMessages[item.id] && item.id !== selected.id" class="text-xs text-red-600">{{ rowMessages[item.id] }}</div>
                        </div>
                        <div class="shrink-0 text-right tabular-nums">
                            <div class="font-semibold">{{ money(item, lineTotal(item)) }}</div>
                            <div v-if="lineSaving(item) > 0" class="text-xs text-gray-400 line-through">{{ money(item, roundMoney(price(item) * quantityOf(item))) }}</div>
                        </div>
                    </li>
                </ul>
                <div class="flex items-center justify-between gap-3 border-t border-gray-200 px-3 py-2 text-sm">
                    <span class="font-medium">{{ ctrans("Total excl. VAT") }}</span>
                    <span class="text-right">
                        <span class="font-bold tabular-nums">{{ money(selected, selectionTotal) }}</span>
                        <span v-if="selectionSaving > 0" class="block text-xs font-medium text-green-600">{{ ctrans("You save :amount", { amount: String(money(selected, selectionSaving)) }) }}</span>
                    </span>
                </div>
            </div>

            <p v-if="accountOfferLabels.length" class="mt-2 text-xs text-gray-500">
                {{ ctrans("Your account discount (:label) is applied in the basket. Discounts do not combine: the bigger one is used.", { label: accountOfferLabels.join(", ") }) }}
            </p>

            <!-- 5. One button saves every size that changed -->
            <div class="mt-4">
                <Button type="primary" size="lg" full :injectStyle="buttonStyle" :loading="isSaving" :disabled="!changedSizes.length" @click="onSave">
                    <template #icon><FontAwesomeIcon :icon="faCartPlus" fixed-width aria-hidden="true" /></template>
                    <template #label>{{ buttonLabel }}</template>
                </Button>
                <div v-if="requestError" class="mt-2 text-xs text-red-600" role="alert">{{ requestError }}</div>
            </div>

            <div v-if="confirmation.length" class="mt-3 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800" role="status">
                <div class="flex items-center gap-2 font-semibold">
                    <FontAwesomeIcon :icon="faCheckCircle" fixed-width aria-hidden="true" /> {{ ctrans("Basket updated") }}
                </div>
                <ul class="ml-6 mt-1 list-disc">
                    <li v-for="line in confirmation" :key="line.size">
                        <template v-if="line.change > 0">{{ ctrans(":change × :size added, :quantity in your basket", { change: String(line.change), size: line.size, quantity: String(line.quantity) }) }}</template>
                        <template v-else-if="line.quantity === 0">{{ ctrans(":size removed from your basket", { size: line.size }) }}</template>
                        <template v-else>{{ ctrans(":size changed to :quantity in your basket", { size: line.size, quantity: String(line.quantity) }) }}</template>
                    </li>
                </ul>
            </div>

            <div v-if="Object.keys(rowMessages).length" class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                <div class="flex items-center gap-2 font-semibold">
                    <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" /> {{ ctrans("Not added, your quantities are kept") }}
                </div>
                <ul class="ml-6 mt-1 list-disc">
                    <li v-for="item in sizes.filter((size) => rowMessages[size.id])" :key="item.id">{{ quantityOf(item) }} × {{ sizeOf(item) }}: {{ rowMessages[item.id] }}</li>
                </ul>
            </div>
        </template>

        <LinkIris v-else :href="urlLoginWithRedirect()" class="mt-4 block w-full rounded border border-gray-400 px-3 py-2 text-center text-sm text-gray-600">
            {{ ctrans("Login or Register for Wholesale Prices") }}
        </LinkIris>
    </div>
</template>

<style scoped>
.variant-step-ordering {
    --step-color: var(--theme-color-4, #c48497);
}

.size-pill.is-selected {
    border-width: 2px;
    border-color: var(--step-color);
    background: color-mix(in srgb, var(--step-color) 6%, white);
}

.qty-badge {
    position: absolute;
    top: 0;
    right: 8px;
    transform: translateY(-50%);
    padding: 1px 8px;
    font-size: 10px;
    font-weight: 700;
    border-radius: 9999px;
    color: white;
    background: var(--step-color);
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

.step-tier {
    border-color: color-mix(in srgb, var(--step-color) 25%, white);
    background: color-mix(in srgb, var(--step-color) 4%, white);
    transition: border-color 0.2s ease, background 0.2s ease;
}

.step-tier:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--step-color) 60%, white);
}

.step-tier:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.step-tier.is-popular {
    border-color: color-mix(in srgb, var(--step-color) 55%, white);
}

.step-tier.is-active {
    border-width: 2px;
    border-color: var(--step-color);
    background: white;
}

.popular-badge {
    position: absolute;
    top: 0;
    right: 16px;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: white;
    background: var(--step-color);
    border-radius: 9999px;
}

.radio {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border-radius: 9999px;
    border: 2px solid color-mix(in srgb, var(--step-color) 40%, #9ca3af);
}

.radio-checked {
    border-color: var(--step-color);
}

.radio-dot {
    width: 10px;
    height: 10px;
    border-radius: 9999px;
}

.radio-checked .radio-dot {
    background: var(--step-color);
}

.step-quantity :deep(.p-inputnumber-button) {
    width: 2.25rem;
}
</style>
