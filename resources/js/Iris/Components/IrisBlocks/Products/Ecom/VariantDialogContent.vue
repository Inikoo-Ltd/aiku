<script setup lang="ts">
import { ProductResource } from '@/types/Iris/Products'
import { ref, inject, computed, watch } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { ctrans } from '@/Composables/useTrans'
import { useLocaleStore } from '@/Stores/locale'
import { urlLoginWithRedirect } from '@/Composables/urlLoginWithRedirect'
import { notify } from '@kyvg/vue3-notification'
import { set } from 'lodash-es'
import axios from 'axios'
import { faCircle, faPlus, faMinus, faMedal } from '@fas'
import { faEnvelopeCircleCheck } from '@fortawesome/free-solid-svg-icons'
import { faEnvelope, faImage } from '@far'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Image from '@common/Components/Image.vue'
import LabelComingSoon from '@/Components/Iris/Products/LabelComingSoon.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import { getBestOffer } from '@/Composables/useOffers'
import DiscountByType from '@/Components/Utils/Label/DiscountByType.vue'

const props = withDefaults(defineProps<{
    variants: ProductResource[]
    variantAxisLabel?: string
    hasInBasketList?: any
    isLoadingRemindBackInStock?: boolean
    selectedProductId?: number | string | null
}>(), {
    variantAxisLabel: '',
    hasInBasketList: () => ({}),
    selectedProductId: null
})

const emits = defineEmits<{
    (e: 'setBackInStock', value: ProductResource): void
    (e: 'unsetBackInStock', value: ProductResource): void
    (e: 'selectVariant', value: ProductResource): void
    (e: 'close'): void
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = useLocaleStore()

const isLoggedIn = computed(() => Boolean(layout?.iris?.is_logged_in))

const activeProduct = ref<ProductResource | null>(
    props.variants.find((variant: ProductResource) => variant.id === props.selectedProductId) ?? props.variants[0] ?? null
)

const basketQuantity = (product: ProductResource): number =>
    Number(props.hasInBasketList?.[product.id]?.quantity_ordered ?? 0)

const quantities = ref<Record<number, number>>({})

const resetQuantities = () => {
    quantities.value = Object.fromEntries(
        props.variants.map((variant: ProductResource) => [variant.id, basketQuantity(variant)])
    )
}

watch(() => props.variants, resetQuantities, { immediate: true })

const isOrderable = (product: ProductResource): boolean =>
    Number(product.stock) > 0 && !product.is_coming_soon

const maxQuantity = (product: ProductResource): number => Number(product.stock) || 0

const setQuantity = (product: ProductResource, value: number) => {
    const quantity = Math.round(Number(value))

    if (Number.isNaN(quantity)) {
        return
    }

    if (activeProduct.value?.id !== product.id) {
        onSelectRow(product)
    }

    if (quantity > maxQuantity(product)) {
        notify({
            title: ctrans('Stock limit reached'),
            text: ctrans('You cannot add more than :stock items.', { stock: String(maxQuantity(product)) }),
            type: 'error'
        })
    }

    quantities.value[product.id] = Math.min(Math.max(0, quantity), maxQuantity(product))
}

const incrementQuantity = (product: ProductResource) => setQuantity(product, (quantities.value[product.id] ?? 0) + 1)

const decrementQuantity = (product: ProductResource) => setQuantity(product, (quantities.value[product.id] ?? 0) - 1)

const onSelectRow = (product: ProductResource) => {
    activeProduct.value = product
    emits('selectVariant', product)
}

const selectedQuantity = (product: ProductResource): number => quantities.value[product.id] ?? 0

const totalItemsSelected = computed<number>(() =>
    props.variants.reduce((total: number, variant: ProductResource) => total + selectedQuantity(variant), 0)
)

const isGoldRewardCustomer = computed<boolean>(() =>
    Boolean(layout?.user?.gr_data?.customer_is_gr || layout?.user?.gr_data?.amnesty)
)

const variantsInFamily = (familyId: number | null | undefined): ProductResource[] =>
    props.variants.filter((variant: ProductResource) => variant.family_id === familyId)

const projectedFamilyQuantity = (familyId: number | null | undefined): number => {
    if (familyId == null) {
        return 0
    }

    const quantityInBasket = Number(layout?.family_quantity_ordered?.[familyId] ?? 0)
    const quantityChange = variantsInFamily(familyId).reduce(
        (total: number, variant: ProductResource) => total + selectedQuantity(variant) - basketQuantity(variant),
        0
    )

    return quantityInBasket + quantityChange
}

const familyWillHaveGoldenProduct = (familyId: number | null | undefined): boolean => {
    if (familyId == null) {
        return false
    }

    return Boolean(layout?.family_has_golden_product?.[familyId])
        || variantsInFamily(familyId).some((variant: ProductResource) => variant.is_golden_product && selectedQuantity(variant) > 0)
}

const offerPercentageOff = (product: ProductResource): number => {
    const bestOffer = getBestOffer(product.offers_data)

    if (!bestOffer) {
        return 0
    }

    const percentageOff = Number(product.offers_data?.best_percentage_off?.percentage_off) || 0

    if (bestOffer.type !== 'Category Quantity Ordered Order Interval') {
        return percentageOff
    }

    const trigger = bestOffer.category_qty_trigger
    const isMemberPriceActive = isGoldRewardCustomer.value
        || Boolean(product.is_golden_product)
        || familyWillHaveGoldenProduct(product.family_id)
        || (trigger != null && Number(trigger) <= projectedFamilyQuantity(product.family_id))

    return isMemberPriceActive ? percentageOff : 0
}

const familyOffer = (product: ProductResource) => {
    const bestOffer = getBestOffer(product.offers_data)

    return bestOffer?.type === 'Category Quantity Ordered Order Interval' ? bestOffer : null
}

const goldPercentageOff = (product: ProductResource): number =>
    Number(product.offers_data?.best_percentage_off?.percentage_off) || 0

const isFamilyOfferLocked = (product: ProductResource): boolean =>
    Boolean(familyOffer(product)) && offerPercentageOff(product) <= 0

const quantityToUnlockFamilyOffer = (product: ProductResource): number => {
    const trigger = Number(familyOffer(product)?.category_qty_trigger)

    if (!trigger) {
        return 0
    }

    return Math.max(0, trigger - projectedFamilyQuantity(product.family_id))
}

const stepSteps = (product: ProductResource): { min_quantity: number, percentage_off: number }[] =>
    [...(product.step_discount?.steps ?? [])].sort((a, b) => a.min_quantity - b.min_quantity)

const stepPercentageOff = (product: ProductResource, quantity: number): number =>
    stepSteps(product).reduce(
        (percentageOff: number, step) => (quantity >= step.min_quantity ? Number(step.percentage_off) || 0 : percentageOff),
        0
    )

const appliedDiscount = (product: ProductResource): { percentageOff: number, source: 'offer' | 'step' | null } => {
    const offer = offerPercentageOff(product)
    const step = stepPercentageOff(product, selectedQuantity(product))

    if (offer <= 0 && step <= 0) {
        return { percentageOff: 0, source: null }
    }

    return offer >= step
        ? { percentageOff: offer, source: 'offer' }
        : { percentageOff: step, source: 'step' }
}

const isGoldPriceDiscount = (product: ProductResource): boolean =>
    appliedDiscount(product).source === 'offer'
    && getBestOffer(product.offers_data)?.type === 'Category Quantity Ordered Order Interval'

const discountLabel = (product: ProductResource): string => {
    if (appliedDiscount(product).source === 'step') {
        return product.step_discount?.label || ctrans('Buy more, save more')
    }

    return isGoldPriceDiscount(product) ? ctrans('Gold price') : ctrans('Offer price')
}

const priceTiers = (product: ProductResource): { minQuantity: number, percentageOff: number, unitPrice: number }[] => {
    const steps = stepSteps(product)

    if (!steps.length) {
        return []
    }

    const offer = offerPercentageOff(product)
    const minQuantities = [...new Set([1, ...steps.map(step => step.min_quantity)])].sort((a, b) => a - b)

    return minQuantities
        .map(minQuantity => {
            const percentageOff = Math.max(offer, stepPercentageOff(product, minQuantity))

            return {
                minQuantity,
                percentageOff,
                unitPrice: roundMoney(Number(product.price_per_unit ?? product.price) * (1 - percentageOff)),
            }
        })
        .filter((tier, index, tiers) => index === 0 || tier.percentageOff > tiers[index - 1].percentageOff)
}

const activeTierMinQuantity = (product: ProductResource): number | null => {
    const quantity = selectedQuantity(product)

    if (quantity <= 0) {
        return null
    }

    return priceTiers(product).findLast(tier => quantity >= tier.minQuantity)?.minQuantity ?? null
}

const percentageLabel = (percentageOff: number): string => `${Math.round(percentageOff * 1000) / 10}%`

const roundMoney = (value: number): number => Math.round(value * 100) / 100

const discountedUnitPrice = (product: ProductResource): number =>
    roundMoney(Number(product.price_per_unit ?? product.price) * (1 - appliedDiscount(product).percentageOff))

const lineTotal = (product: ProductResource): number =>
    roundMoney(selectedQuantity(product) * Number(product.price) * (1 - appliedDiscount(product).percentageOff))

const grossTotal = computed<number>(() =>
    roundMoney(props.variants.reduce((total: number, variant: ProductResource) => total + selectedQuantity(variant) * Number(variant.price), 0))
)

const netTotal = computed<number>(() =>
    roundMoney(props.variants.reduce((total: number, variant: ProductResource) => total + lineTotal(variant), 0))
)

const totalSaving = computed<number>(() => roundMoney(grossTotal.value - netTotal.value))

const changedVariants = computed<ProductResource[]>(() =>
    props.variants.filter((variant: ProductResource) => (quantities.value[variant.id] ?? 0) !== basketQuantity(variant))
)

const stockLabel = (product: ProductResource): string => {
    if (Number(product.stock) >= 250) {
        return ctrans('Unlimited quantity')
    }

    return ctrans(':stock items', { stock: String(Math.max(0, Number(product.stock) || 0)) })
}

const unitsLabel = (product: ProductResource): string =>
    Number(product.units) > 1
        ? ctrans('Pack of :units', { units: String(product.units) })
        : ctrans('Sold individually')

const formatPrice = (value: number | string | undefined) =>
    locale.currencyFormat(activeProduct.value?.currency_code ?? layout?.iris?.currency?.code, Number(value) || 0)

const updateBasketList = (product: ProductResource, payload: any, quantity: number) => {
    const currentList = layout.family_page?.productInBasket?.list || {}

    set(layout, ['family_page', 'productInBasket', 'list'], {
        ...currentList,
        [product.id]: {
            ...(currentList[product.id] ?? {}),
            transaction_id: quantity > 0 ? payload.transaction_id ?? currentList[product.id]?.transaction_id : null,
            quantity_ordered: payload.quantity_ordered ?? quantity,
            quantity_ordered_new: payload.quantity_ordered ?? quantity,
            department_id: payload.department_id,
            sub_department_id: payload.sub_department_id,
            family_id: payload.family_id,
            is_golden_product: payload.is_golden_product,
        }
    })
}

const saveVariantQuantity = async (product: ProductResource) => {
    const quantity = quantities.value[product.id] ?? 0
    const transactionId = props.hasInBasketList?.[product.id]?.transaction_id

    const response = transactionId
        ? await axios.post(route('iris.models.transaction.update', { transaction: transactionId }), { quantity_ordered: quantity })
        : await axios.post(route('iris.models.transaction.store', { product: product.id }), { quantity })

    updateBasketList(product, response.data, quantity)
}

const isSaving = ref(false)

const onSave = async () => {
    if (!changedVariants.value.length) {
        emits('close')
        return
    }

    isSaving.value = true

    const results = await Promise.allSettled(changedVariants.value.map(saveVariantQuantity))
    const failed = results.filter(result => result.status === 'rejected') as PromiseRejectedResult[]

    layout.reload_handle?.()
    isSaving.value = false

    if (failed.length) {
        notify({
            title: ctrans('Something went wrong'),
            text: failed[0].reason?.response?.data?.message || ctrans('Failed to update product quantity'),
            type: 'error'
        })
        resetQuantities()
        return
    }

    notify({
        title: ctrans('Success'),
        text: ctrans('Basket updated'),
        type: 'success'
    })
    emits('close')
}

const onCancel = () => {
    resetQuantities()
    emits('close')
}
</script>

<template>
    <div class="variant-dialog flex flex-col">
        <div class="grid grid-cols-1 md:grid-cols-[minmax(0,240px)_1fr] gap-5 md:gap-6">
            <div class="md:border-r md:border-gray-200 md:pr-6">
                <div class="aspect-square w-full overflow-hidden rounded-lg bg-gray-50 max-w-[220px] md:max-w-none mx-auto">
                    <Image v-if="activeProduct?.web_images?.main?.original" :src="activeProduct.web_images.main.original"
                        :alt="activeProduct?.name" class="h-full w-full object-contain" />
                    <div v-else class="flex h-full w-full items-center justify-center">
                        <FontAwesomeIcon :icon="faImage" class="text-3xl text-gray-300" fixed-width />
                    </div>
                </div>

                <div v-if="activeProduct" class="mt-3 text-center md:text-left">
                    <div class="font-medium text-gray-900">{{ activeProduct.code }}</div>
                    <div class="text-sm text-gray-500">{{ unitsLabel(activeProduct) }}</div>
                </div>
            </div>

            <div class="min-w-0">
                <p class="mb-3 text-sm text-gray-600">
                    {{ ctrans('Choose how many items you would like in each :axis.', { axis: (variantAxisLabel || ctrans('variant')).toLowerCase() }) }}
                </p>

                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <div class="variant-row variant-row-head bg-gray-50 text-sm font-medium text-gray-700">
                        <div>{{ variantAxisLabel || ctrans('Variant') }}</div>
                        <div class="hidden sm:block">{{ ctrans('Product code') }}</div>
                        <div v-if="isLoggedIn">{{ ctrans('Stock') }}</div>
                        <div v-if="isLoggedIn" class="text-center">{{ ctrans('Quantity') }}</div>
                    </div>

                    <div v-for="variant in variants" :key="variant.id"
                        class="variant-row cursor-pointer border-t border-gray-200 transition"
                        :class="activeProduct?.id === variant.id ? 'variant-row-active' : 'hover:bg-gray-50'"
                        :aria-selected="activeProduct?.id === variant.id"
                        @click="onSelectRow(variant)">
                        <div class="min-w-0">
                            <div class="variant-row-label text-base font-semibold text-gray-900">{{ variant.variant_label || variant.code }}</div>
                            <span v-if="isLoggedIn && familyOffer(variant)" class="gold-badge mt-1"
                                :class="{ 'gold-badge-off': isFamilyOfferLocked(variant) }">
                                <FontAwesomeIcon :icon="faMedal" fixed-width aria-hidden="true" />
                                -{{ percentageLabel(goldPercentageOff(variant)) }}
                            </span>
                            <div v-else-if="isLoggedIn && selectedQuantity(variant) > 0 && appliedDiscount(variant).percentageOff > 0"
                                class="text-xs font-medium text-orange-500">
                                -{{ percentageLabel(appliedDiscount(variant).percentageOff) }}
                            </div>
                        </div>

                        <div class="hidden sm:block text-sm text-gray-500 truncate">{{ variant.code }}</div>

                        <div v-if="isLoggedIn" class="flex items-center gap-2 text-sm text-gray-600">
                            <LabelComingSoon v-if="variant.is_coming_soon" :product="variant" />
                            <template v-else>
                                <FontAwesomeIcon :icon="faCircle" class="shrink-0 text-[8px]"
                                    :class="Number(variant.stock) > 0 ? 'text-green-600' : 'text-red-600'" fixed-width />
                                <span>{{ Number(variant.stock) > 0 ? stockLabel(variant) : ctrans('Out of stock') }}</span>
                            </template>
                        </div>

                        <div v-if="isLoggedIn" class="flex justify-center" @click.stop>
                            <div v-if="isOrderable(variant)" class="qty-stepper">
                                <button type="button" class="qty-stepper-button" :disabled="isSaving || !quantities[variant.id]"
                                    :aria-label="ctrans('Decrease quantity')" @click="decrementQuantity(variant)">
                                    <FontAwesomeIcon :icon="faMinus" fixed-width />
                                </button>
                                <input type="number" min="0" :max="maxQuantity(variant)" inputmode="numeric"
                                    class="qty-stepper-input" :value="quantities[variant.id] ?? 0" :disabled="isSaving"
                                    :aria-label="ctrans('Quantity')"
                                    @focus="activeProduct?.id !== variant.id && onSelectRow(variant)"
                                    @change="setQuantity(variant, Number(($event.target as HTMLInputElement).value))" />
                                <button type="button" class="qty-stepper-button"
                                    :disabled="isSaving || (quantities[variant.id] ?? 0) >= maxQuantity(variant)"
                                    :aria-label="ctrans('Increase quantity')" @click="incrementQuantity(variant)">
                                    <FontAwesomeIcon :icon="faPlus" fixed-width />
                                </button>
                            </div>

                            <button v-else-if="layout?.outboxes?.oos_notification?.state == 'active'" type="button"
                                class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 transition hover:bg-gray-100"
                                @click="variant.is_back_in_stock ? emits('unsetBackInStock', variant) : emits('setBackInStock', variant)">
                                <LoadingIcon v-if="isLoadingRemindBackInStock" />
                                <FontAwesomeIcon v-else :icon="variant.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                                    :class="variant.is_back_in_stock ? 'text-green-600' : 'text-gray-600'" fixed-width />
                                <span>{{ variant.is_back_in_stock ? ctrans('Notified') : ctrans('Remind me') }}</span>
                            </button>

                            <span v-else class="text-sm text-gray-400">—</span>
                        </div>
                    </div>
                </div>

                <div v-if="isLoggedIn && activeProduct" class="mt-3 space-y-2">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <template v-if="appliedDiscount(activeProduct).percentageOff > 0">
                            <span class="text-lg font-bold text-orange-500">{{ formatPrice(discountedUnitPrice(activeProduct)) }} / {{ activeProduct.unit }}</span>
                            <span class="text-sm text-gray-400 line-through">{{ formatPrice(activeProduct.price_per_unit ?? activeProduct.price) }}</span>
                            <span v-if="!isGoldPriceDiscount(activeProduct)" class="text-xs text-orange-500">
                                {{ discountLabel(activeProduct) }} · -{{ percentageLabel(appliedDiscount(activeProduct).percentageOff) }}
                            </span>
                        </template>
                        <span v-else class="text-lg font-bold text-gray-900">{{ formatPrice(activeProduct.price_per_unit ?? activeProduct.price) }} / {{ activeProduct.unit }}</span>
                        <span class="text-xs text-gray-500">{{ ctrans('excl. VAT') }}</span>
                        <span v-if="familyOffer(activeProduct)" class="gold-badge self-center"
                            :class="{ 'gold-badge-off': isFamilyOfferLocked(activeProduct) }">
                            <FontAwesomeIcon :icon="faMedal" fixed-width aria-hidden="true" />
                            {{ ctrans('Gold price') }} -{{ percentageLabel(goldPercentageOff(activeProduct)) }}
                        </span>
                    </div>

                    <div v-if="isFamilyOfferLocked(activeProduct) && isOrderable(activeProduct)" class="flex flex-wrap items-center gap-2">
                        <div class="w-fit max-w-full">
                            <DiscountByType template="products_triggers_label" :offers_data="activeProduct.offers_data"
                                :isGoldenProduct="activeProduct.is_golden_product" />
                        </div>
                        <span v-if="quantityToUnlockFamilyOffer(activeProduct) > 0" class="text-xs text-gray-500">
                            {{ ctrans(':quantity more to unlock', { quantity: String(quantityToUnlockFamilyOffer(activeProduct)) }) }}
                        </span>
                    </div>

                    <div v-if="priceTiers(activeProduct).length > 1" class="flex flex-wrap gap-1.5">
                        <button v-for="tier in priceTiers(activeProduct)" :key="tier.minQuantity" type="button"
                            class="price-tier" :class="{ 'price-tier-active': activeTierMinQuantity(activeProduct) === tier.minQuantity }"
                            :disabled="isSaving || !isOrderable(activeProduct) || tier.minQuantity > maxQuantity(activeProduct)"
                            @click="setQuantity(activeProduct, tier.minQuantity)">
                            <span class="text-gray-500">{{ tier.minQuantity }}+</span>
                            <span class="font-semibold text-gray-900">{{ formatPrice(tier.unitPrice) }}</span>
                            <span v-if="tier.percentageOff > 0" class="text-orange-500">-{{ percentageLabel(tier.percentageOff) }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 flex flex-col-reverse gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <div v-if="isLoggedIn">
                <div class="text-lg font-bold text-gray-900 sm:text-2xl">
                    {{ ctrans(':count items selected', { count: String(totalItemsSelected) }) }}
                </div>
                <div v-if="totalItemsSelected > 0" class="mt-0.5 text-sm text-gray-600">
                    {{ ctrans('Total') }}:
                    <span v-if="totalSaving > 0" class="mr-1 text-gray-400 line-through">{{ formatPrice(grossTotal) }}</span>
                    <span class="font-semibold text-gray-900">{{ formatPrice(netTotal) }}</span>
                    {{ ctrans('excl. VAT') }}
                    <span v-if="totalSaving > 0" class="ml-1 text-orange-500">
                        · {{ ctrans('You save :amount', { amount: formatPrice(totalSaving) }) }}
                    </span>
                </div>
            </div>

            <div class="flex gap-3 sm:ml-auto">
                <button type="button" :disabled="isSaving"
                    class="flex-1 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium sm:px-6 sm:py-2.5 sm:text-base text-gray-800 transition hover:bg-gray-50 sm:flex-none"
                    @click="onCancel">
                    {{ ctrans('Cancel') }}
                </button>

                <button v-if="isLoggedIn" type="button" :disabled="isSaving"
                    class="button-primary flex flex-1 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium sm:px-6 sm:py-2.5 sm:text-base transition sm:flex-none"
                    @click="onSave">
                    <LoadingIcon v-if="isSaving" />
                    {{ ctrans('Save Selection') }}
                </button>

                <LinkIris v-else :href="urlLoginWithRedirect()"
                    class="button-primary flex flex-1 items-center justify-center rounded-md px-4 py-2 text-sm font-medium sm:px-6 sm:py-2.5 sm:text-base sm:flex-none">
                    {{ ctrans('Login or Register for Wholesale Prices') }}
                </LinkIris>
            </div>
        </div>
    </div>
</template>

<style scoped>
.gold-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    width: fit-content;
    border-radius: 9999px;
    padding: 0.125rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1rem;
    color: #E87928;
    background-color: #FDF1E8;
}

.gold-badge-off {
    color: #9ca3af;
    background-color: #f3f4f6;
}

.variant-row {
    display: grid;
    grid-template-columns: minmax(40px, 0.6fr) minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
}

@media (min-width: 640px) {
    .variant-row {
        grid-template-columns: minmax(48px, 0.7fr) minmax(0, 1fr) minmax(0, 1fr) auto;
        gap: 12px;
        padding: 10px 16px;
    }
}

.variant-row-active {
    background-color: color-mix(in srgb, var(--theme-color-4) 8%, white);
    box-shadow: inset 4px 0 0 var(--theme-color-4);
}

.variant-row-active .variant-row-label {
    color: var(--theme-color-4);
}

.variant-row-head {
    padding-top: 12px;
    padding-bottom: 12px;
}

.qty-stepper {
    display: inline-flex;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
}

.qty-stepper-button {
    padding: 4px 8px;
    font-size: 12px;
    color: #111827;
    transition: background-color 0.15s ease;
}

.qty-stepper-button:hover:not(:disabled) {
    background-color: #f3f4f6;
}

.qty-stepper-button:disabled {
    color: #d1d5db;
    cursor: not-allowed;
}

.qty-stepper-input {
    width: 36px;
    border: 0;
    border-left: 1px solid #d1d5db;
    border-right: 1px solid #d1d5db;
    padding: 0;
    text-align: center;
    font-size: 14px;
    -moz-appearance: textfield;
}

@media (min-width: 640px) {
    .qty-stepper-button {
        padding: 8px 12px;
        font-size: 14px;
    }

    .qty-stepper-input {
        width: 52px;
        font-size: 16px;
    }
}

.qty-stepper-input:focus {
    outline: none;
    box-shadow: none;
}

.qty-stepper-input::-webkit-outer-spin-button,
.qty-stepper-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.price-tier {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 9999px;
    background: white;
    font-size: 12px;
    white-space: nowrap;
    transition: border-color 0.15s ease;
}

.price-tier:hover:not(:disabled) {
    border-color: #9ca3af;
}

.price-tier:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.price-tier-active {
    border-color: var(--theme-color-4);
    box-shadow: 0 0 0 1px var(--theme-color-4);
}

.button-primary {
    background-color: var(--theme-color-4);
    color: var(--theme-color-5);
}

.button-primary:hover:not(:disabled) {
    background-color: color-mix(in srgb, var(--theme-color-4) 85%, black);
}

.button-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
</style>
