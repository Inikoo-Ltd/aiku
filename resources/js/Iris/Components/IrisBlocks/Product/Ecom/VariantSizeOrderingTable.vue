<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import axios from "axios"
import { set } from "lodash-es"
import { createReusableTemplate } from "@vueuse/core"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fal"
import { faCircle } from "@fas"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import LabelComingSoon from "@/Components/Iris/Products/LabelComingSoon.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { getBestOffer } from "@/Composables/useOffers"
import { pendingChangeLabel, pendingChanges, sizeBasketButtonLabel, type SizeQuantityChange } from "@/Composables/sizeBasketAction"

library.add(faCheck, faCircle)

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
}>(), {
    axisLabel: "",
    productName: "",
    isLoggedIn: false
})

const layout = inject<any>("layout", retinaLayoutStructure)
const customerDataByProduct = inject<any>("variantCustomerData", null)
const patchCustomerData = inject<((productId: number, patch: Record<string, any>) => void) | null>("patchVariantCustomerData", null)
const locale = useLocaleStore()

const [DefineQuantity, ReuseQuantity] = createReusableTemplate<{ product: SizeProduct }>()
const [DefinePrice, ReusePrice] = createReusableTemplate<{ product: SizeProduct }>()
const [DefineAvailability, ReuseAvailability] = createReusableTemplate<{ product: SizeProduct }>()

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

const quantities = ref<Record<number, number>>({})
const touched = ref<Set<number>>(new Set())
const limitHints = ref<Record<number, string>>({})
const rowMessages = ref<Record<number, string>>({})

const syncQuantities = () => {
    const next = { ...quantities.value }

    props.listProducts.forEach((product) => {
        if (!touched.value.has(product.id)) {
            next[product.id] = basketQuantity(product)
        }
    })

    quantities.value = next
}

watch(
    () => [props.listProducts, customerDataByProduct?.value],
    syncQuantities,
    { immediate: true, deep: true }
)

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

const selectedQuantity = (product: SizeProduct): number => quantities.value[product.id] ?? 0

const setQuantity = (product: SizeProduct, value: number | null) => {
    const quantity = Math.max(0, Math.round(Number(value) || 0))
    const max = maxQuantity(product)

    touched.value.add(product.id)
    delete rowMessages.value[product.id]

    if (quantity > max) {
        limitHints.value[product.id] = ctrans("You cannot add more than :stock items.", { stock: String(max) })
        quantities.value[product.id] = max
        return
    }

    delete limitHints.value[product.id]
    quantities.value[product.id] = quantity
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

const tiersLabel = (product: SizeProduct): string =>
    tierSteps(product)
        .map((step) => ctrans(":quantity+ containers: :percentage off", {
            quantity: String(step.min_quantity),
            percentage: percentageLabel(Number(step.percentage_off))
        }))
        .join(" · ")

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

const quantityChanges = computed<SizeQuantityChange[]>(() =>
    props.listProducts
        .filter((product) => rowCanChange(product))
        .map((product) => ({ size: sizeOf(product), selected: selectedQuantity(product), basket: basketQuantity(product) }))
)

const buttonLabel = computed<string>(() => {
    const label = sizeBasketButtonLabel(quantityChanges.value)

    return ctrans(label.text, label.replacements)
})

const pendingSummary = computed<string>(() => pendingChanges(quantityChanges.value).map(pendingChangeLabel).join(" · "))

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
const confirmation = ref<{ quantity: number, size: string }[]>([])

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
        touched.value.delete(product.id)
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

        const added: { quantity: number, size: string }[] = []

        response.data.lines.forEach((line: any) => {
            const product = props.listProducts.find((item) => item.id === line.product_id)

            if (!product) {
                return
            }

            if (line.status === "failed") {
                rowMessages.value[product.id] = line.message || ctrans("Failed to update product quantity")
                return
            }

            touched.value.delete(product.id)
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
                added.push({ quantity: line.quantity_ordered, size: sizeOf(product) })
            }
        })

        const keptIds = new Set(response.data.lines.filter((line: any) => line.status === "added" || line.status === "updated").map((line: any) => line.product_id))

        savedAmounts.value = Object.fromEntries(Object.entries(savedAmounts.value).filter(([id]) => keptIds.has(Number(id))))

        confirmation.value = added
        syncQuantities()
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
                <InputNumber :modelValue="quantities[product.id] ?? 0" :min="0" :max="maxQuantity(product) === Infinity ? undefined : maxQuantity(product)"
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

        <DefinePrice v-slot="{ product }">
            <div v-if="price(product) > 0">
                <div class="font-semibold text-gray-900">
                    <span v-if="isLoggedIn && hasDiscountApplied(product)" class="mr-1 text-xs font-normal text-gray-400 line-through">{{ formatPrice(product, price(product)) }}</span>
                    <span>{{ formatPrice(product, isLoggedIn ? containerPrice(product) : price(product)) }}</span>
                </div>
                <div v-if="pricePerLitre(product) !== null" class="text-xs text-gray-500">
                    {{ ctrans(":price per litre", { price: String(formatPrice(product, pricePerLitre(product) as number)) }) }}
                </div>
                <div v-if="tiersLabel(product)" class="mt-0.5 text-xs text-gray-500">{{ tiersLabel(product) }}</div>
            </div>
            <span v-else class="text-sm text-gray-500">{{ ctrans("Price not available") }}</span>
        </DefinePrice>

        <DefineAvailability v-slot="{ product }">
            <LabelComingSoon v-if="product.is_coming_soon && !product.pre_order" :product="product" />
            <div v-else class="flex items-center gap-2 text-sm text-gray-600">
                <FontAwesomeIcon icon="fas fa-circle" class="shrink-0 text-[8px]" :class="availabilityTone(product)" fixed-width aria-hidden="true" />
                <span :class="{ 'text-red-600': availabilityTone(product) === 'text-red-600' }">{{ availabilityText(product) }}</span>
            </div>
        </DefineAvailability>

        <div class="mb-2 text-sm font-semibold text-gray-800">{{ axisLabel || ctrans("Size") }}</div>
        <p v-if="hasSizes" class="mb-3 text-xs text-gray-500">{{ ctrans("Size is the amount in each container. Quantity is the number of containers.") }}</p>

        <template v-if="isLoggedIn">
            <table class="sizes-table w-full text-left text-sm">
                <thead class="whitespace-nowrap border-b border-gray-300 text-gray-700">
                    <tr>
                        <th class="py-2 pr-3 font-medium">{{ hasSizes ? ctrans("Size") : (axisLabel || ctrans("Option")) }}</th>
                        <th class="py-2 pr-3 font-medium">{{ ctrans("Price excl. VAT") }}</th>
                        <th class="py-2 pr-3 font-medium">{{ ctrans("Availability") }}</th>
                        <th class="py-2 pr-3 font-medium">{{ ctrans("Quantity") }}</th>
                        <th class="py-2 text-right font-medium">{{ ctrans("Line total") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="product in listProducts" :key="product.id">
                        <tr class="border-b border-gray-200 align-top">
                            <td class="py-3 pr-3">
                                <div class="font-semibold text-gray-900">{{ sizeOf(product) }}</div>
                                <div class="whitespace-nowrap text-xs text-gray-400">{{ product.code }}</div>
                            </td>
                            <td class="py-3 pr-3"><ReusePrice :product="product" /></td>
                            <td class="py-3 pr-3"><ReuseAvailability :product="product" /></td>
                            <td class="py-3 pr-3"><ReuseQuantity :product="product" /></td>
                            <td class="py-3 text-right font-semibold tabular-nums">
                                <template v-if="showsLineTotal(product)">{{ formatPrice(product, lineTotal(product)) }}</template>
                                <template v-else>—</template>
                            </td>
                        </tr>
                        <tr v-if="rowMessages[product.id]">
                            <td colspan="5" class="pb-2 text-xs text-red-600">{{ rowMessages[product.id] }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div class="sizes-cards space-y-3">
                <div v-for="product in listProducts" :key="product.id" class="rounded-lg border border-gray-200 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-gray-900">{{ sizeOf(product) }}</div>
                            <div class="whitespace-nowrap text-xs text-gray-400">{{ product.code }}</div>
                        </div>
                        <div class="text-right"><ReusePrice :product="product" /></div>
                    </div>
                    <div class="mt-2"><ReuseAvailability :product="product" /></div>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <ReuseQuantity :product="product" />
                        <div class="font-semibold tabular-nums">
                            <template v-if="showsLineTotal(product)">{{ formatPrice(product, lineTotal(product)) }}</template>
                            <template v-else>—</template>
                        </div>
                    </div>
                    <div v-if="rowMessages[product.id]" class="mt-2 text-xs text-red-600">{{ rowMessages[product.id] }}</div>
                </div>
            </div>

            <p v-if="hasTiers" class="mt-3 text-xs text-gray-500">
                {{ ctrans("Volume discounts count the containers of one size; different sizes are not added together.") }}
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

                <Button type="tertiary" full :loading="isSaving" :disabled="!changedProducts.length" @click="onSave">
                    <template #loading><LoadingIcon v-if="isSaving" /></template>
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                    <template #label>{{ buttonLabel }}</template>
                </Button>

                <div v-if="pendingSummary && !isSaving" class="text-xs text-amber-600">
                    {{ ctrans("Not in your basket yet: :changes", { changes: pendingSummary }) }}
                </div>

                <div v-if="requestError" class="text-xs text-red-600">{{ requestError }}</div>

                <div v-if="confirmation.length" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800" role="status">
                    <div class="font-semibold">{{ productName }}</div>
                    <ul class="mt-1 space-y-0.5">
                        <li v-for="line in confirmation" :key="line.size">{{ ctrans(":quantity × :size", { quantity: String(line.quantity), size: line.size }) }}</li>
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
    container-type: inline-size;
}

.variant-size-ordering table.sizes-table {
    display: none;
}

@container (min-width: 440px) {
    .variant-size-ordering table.sizes-table {
        display: table;
    }

    .variant-size-ordering .sizes-cards {
        display: none;
    }
}

.variant-size-ordering table.sizes-table {
    margin: 0;
    table-layout: auto;
    border-width: 0;
}

.variant-size-ordering table.sizes-table th,
.variant-size-ordering table.sizes-table td {
    border-width: 0 0 1px;
    background-color: transparent;
    padding: 0.5rem 0.5rem 0.5rem 0;
}

.variant-size-ordering table.sizes-table th {
    font-size: 0.75rem;
}

.variant-size-ordering table.sizes-table th:last-child,
.variant-size-ordering table.sizes-table td:last-child {
    padding-right: 0;
}

.sizes-quantity :deep(.p-inputnumber-button) {
    width: 1.75rem;
}
</style>
