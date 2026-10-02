<script setup lang="ts">
import { computed, inject, reactive, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRedoAlt, faShoppingBasket, faGift, faBell, faHeart, faFileInvoice, faArrowRight, faCheck, faMinus, faPlus, faTicketAlt, faCopy, faChevronLeft, faChevronRight } from "@fal"
import { faBell as fasBell } from "@fas"
import { Swiper, SwiperSlide } from "swiper/vue"
import "swiper/css"
import type { Swiper as SwiperInstance } from "swiper"
import Image from "@common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ProductCardEcom3 from "@/Iris/Components/IrisBlocks/Products/Ecom/ProductCard/ProductCardEcom3.vue"
import Select from "primevue/select"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faRedoAlt, faShoppingBasket, faGift, faBell, fasBell, faHeart, faFileInvoice, faArrowRight, faCheck, faMinus, faPlus, faTicketAlt, faCopy)

type StockStatus = "in_stock" | "low" | "out_of_stock" | "unavailable"

interface Regular {
    id: number
    code: string
    name: string
    image: Record<string, string> | null
    url: string | null
    price: number
    unit: string | null
    units: number
    available_quantity: number
    is_on_demand: boolean
    stock_status: StockStatus
    eta: string | null
    has_reminder: boolean
    is_purchasable: boolean
    orders: number
    quantity: number
    spend: number
    average_quantity: number
    last_ordered_at: string
    reorder_every_days: number | null
    due_at: string | null
    days_until_due: number | null
    quantity_in_basket: number
}

interface Favourite {
    id: number
    code: string
    name: string
    image: Record<string, string> | null
    url: string | null
    available_quantity: number
    stock_status: StockStatus
    is_purchasable: boolean
    has_reminder: boolean
    quantity_in_basket: number
}

interface RecentOrder {
    id: number
    slug: string
    reference: string
    date: string
    state: string
    state_label: string
    total: number
    items: number
    invoice: string | null
}

interface Voucher {
    code: string
    name: string
    percentage_off: number | null
    amount_off: number | null
    is_free_shipping: boolean
    is_gift: boolean
    is_whole_order: boolean
    min_amount: number | null
    expires_at: string | null
}

interface Insights {
    currency_code: string
    kpis: {
        orders: number
        total_orders: number
        average_order: number | null
        order_every_days: number | null
        last_order_at: string | null
        days_since_last: number | null
        next_order_due_at: string | null
        is_lapsed: boolean
    }
    gold_reward: { label: string, days_left: number, expires_at: string } | null
    vouchers: Voucher[]
    regulars: Regular[]
    favourites: Favourite[]
    recent_orders: RecentOrder[]
    recommendations: Record<string, any>[]
    recommendations_source: "bought_together" | "shop_best_sellers"
}

const props = defineProps<{
    insights: Insights
    readOnly?: boolean
}>()

const layout = inject("layout", {})
const locale = useLocaleStore()

/**
 * ponytail: heavy buyers get more rows to reorder from; 50 orders a year is roughly one a week.
 */
const HEAVY_BUYER_ORDERS = 50

const money = (amount: number | null | undefined) => locale.currencyFormat(props.insights.currency_code, amount ?? 0)

const languageCode = computed(() => locale.language?.code || undefined)

const todayIso = new Date().toISOString().slice(0, 10)
const currentYear = new Date().getUTCFullYear()

const formatDate = (iso: string | null, options: Intl.DateTimeFormatOptions) => {
    const date = iso ? new Date(iso) : null
    if (!date || isNaN(date.getTime())) return ""
    return new Intl.DateTimeFormat(languageCode.value, { timeZone: "UTC", ...options }).format(date)
}

const shortDate = (iso: string | null) => formatDate(iso, {
    day: "numeric",
    month: "short",
    ...(iso && new Date(iso).getUTCFullYear() !== currentYear ? { year: "numeric" } : {}),
})

const longDate = (iso: string) => formatDate(iso, { day: "numeric", month: "long", year: "numeric" })

const percent = (fraction: number) => new Intl.NumberFormat(languageCode.value, { style: "percent", maximumFractionDigits: 1 }).format(fraction)

const hasHistory = computed(() => props.insights.kpis.total_orders > 0)
const isLapsed = computed(() => props.insights.kpis.is_lapsed)
const lastOrder = computed(() => props.insights.recent_orders[0] ?? null)

const heading = computed(() => {
    if (!hasHistory.value) {
        return { title: ctrans("Welcome to your trade account"), subtitle: ctrans("Manage your orders and discover products for your business.") }
    }
    if (isLapsed.value) {
        return { title: ctrans("Welcome back"), subtitle: ctrans("Your previous products and favourites are ready when you are.") }
    }
    return { title: ctrans("Your dashboard"), subtitle: ctrans("Quick access to your orders and favourite products.") }
})

const orderAgainSortRank = (regular: Regular) => {
    if (!regular.is_purchasable || regular.stock_status === "unavailable") return 4
    if (regular.stock_status === "out_of_stock") return 3
    if (regular.stock_status === "low" || (regular.days_until_due !== null && regular.days_until_due <= 7)) return 0
    return 1
}

const orderableRegulars = computed(() => props.insights.regulars.filter((regular) => regular.stock_status !== "unavailable"))

const pickedIds = ref<number[]>([])

const orderAgainRows = computed(() => {
    const limit = props.insights.kpis.orders >= HEAVY_BUYER_ORDERS ? 10 : 5
    const picked = pickedIds.value
        .map((id) => orderableRegulars.value.find((regular) => regular.id === id))
        .filter((regular): regular is Regular => !!regular)
    const suggested = orderableRegulars.value
        .filter((regular) => !pickedIds.value.includes(regular.id))
        .map((regular, index) => ({ regular, index }))
        .sort((a, b) => orderAgainSortRank(a.regular) - orderAgainSortRank(b.regular) || a.index - b.index)
        .slice(0, Math.max(0, limit - picked.length))
        .map(({ regular }) => regular)
    return [...picked, ...suggested]
})

const pickerOptions = computed(() => orderableRegulars.value.filter((regular) => !orderAgainRows.value.some((row) => row.id === regular.id)))

const pickProduct = (id: number | null) => {
    if (id && !pickedIds.value.includes(id)) {
        pickedIds.value = [id, ...pickedIds.value]
    }
}

const packLine = (price: number, units: number | null | undefined, unit: string | null | undefined) => {
    const unitLabel = unit || ctrans("unit")
    if (!units || units <= 1) {
        return ctrans(":price per :unit", { price: money(price), unit: unitLabel })
    }
    return ctrans(":price for :count · :unit_price per :unit", {
        price: money(price),
        count: String(Number(units.toFixed(3))),
        unit_price: money(price / units),
        unit: unitLabel,
    })
}

const canAdd = (item: { is_purchasable: boolean, stock_status: StockStatus }) => item.is_purchasable && (item.stock_status === "in_stock" || item.stock_status === "low")

const suggestedQuantity = (regular: Regular) => regular.stock_status === "low"
    ? Math.max(1, Math.min(regular.average_quantity, regular.available_quantity))
    : regular.average_quantity

const quantities = reactive<Record<number, number>>({})

const quantityFor = (id: number, fallback: number) => quantities[id] ?? fallback

const stepQuantity = (id: number, fallback: number, step: number, max?: number) => {
    const next = Math.max(1, quantityFor(id, fallback) + step)
    quantities[id] = max && max > 0 ? Math.min(next, max) : next
}

const setQuantity = (id: number, input: HTMLInputElement, max?: number) => {
    const parsed = Math.max(1, Math.floor(Number(input.value) || 1))
    quantities[id] = max && max > 0 ? Math.min(parsed, max) : parsed
    input.value = String(quantities[id])
}

const maxQuantity = (regular: Regular) => regular.is_on_demand ? undefined : regular.available_quantity

const rowHint = (regular: Regular) => {
    if (regular.stock_status === "out_of_stock") {
        return regular.eta && regular.eta >= todayIso ? ctrans("Back :date", { date: shortDate(regular.eta) }) : ctrans("Arrival date to be confirmed")
    }
    if (regular.stock_status === "low") {
        return ctrans("Only :count left, order before it runs out", { count: String(regular.available_quantity) })
    }
    if (isLapsed.value || regular.days_until_due === null) {
        return ctrans("Usually :count", { count: String(regular.average_quantity) })
    }
    if (regular.days_until_due < 0) {
        return ctrans("Due for restock · usually :count", { count: String(regular.average_quantity) })
    }
    if (regular.days_until_due === 0) {
        return ctrans("Due today · usually :count", { count: String(regular.average_quantity) })
    }
    if (regular.days_until_due === 1) {
        return ctrans("Due tomorrow · usually :count", { count: String(regular.average_quantity) })
    }
    if (regular.days_until_due <= 7) {
        return ctrans("Due in :days days · usually :quantity", { days: String(regular.days_until_due), quantity: String(regular.average_quantity) })
    }
    return ctrans("Usually :count", { count: String(regular.average_quantity) })
}

const stockChip = (status: StockStatus) => {
    switch (status) {
        case "low":
            return { label: ctrans("Low stock"), class: "bg-amber-50 text-amber-800 ring-amber-600/20" }
        case "out_of_stock":
            return { label: ctrans("Awaiting stock"), class: "bg-sky-50 text-sky-800 ring-sky-600/20" }
        case "unavailable":
            return { label: ctrans("No longer available"), class: "bg-gray-100 text-gray-600 ring-gray-500/20" }
        default:
            return { label: ctrans("In stock"), class: "bg-emerald-50 text-emerald-800 ring-emerald-600/20" }
    }
}

type BasketItem = { id: number, quantity_in_basket?: number }

const basketQuantities = reactive<Record<number, number>>({})

const inBasket = (item: BasketItem) => basketQuantities[item.id] ?? item.quantity_in_basket ?? 0

const addingProductIds = ref<number[]>([])

const addToBasket = async (item: BasketItem, quantity: number) => {
    if (props.readOnly || addingProductIds.value.includes(item.id)) return
    addingProductIds.value.push(item.id)
    const newQuantity = inBasket(item) + quantity
    try {
        const { data } = await axios.post(route("retina.models.product.add-to-basket", { product: item.id }), {
            quantity: newQuantity,
        })
        basketQuantities[item.id] = data?.quantity_ordered ?? newQuantity
        notify({ title: ctrans("Added to basket"), type: "success" })
        delete quantities[item.id]
        layout?.reload_handle?.()
    } catch (error: any) {
        notify({
            title: ctrans("Could not add to basket"),
            text: error?.response?.data?.message || ctrans("Please try again"),
            type: "error",
        })
    } finally {
        addingProductIds.value = addingProductIds.value.filter((id) => id !== item.id)
    }
}

const togglingReminderIds = ref<number[]>([])

const toggleReminder = async (favourite: { id: number, has_reminder: boolean }) => {
    if (props.readOnly) return
    togglingReminderIds.value.push(favourite.id)
    try {
        if (favourite.has_reminder) {
            await axios.delete(route("retina.models.remind_back_in_stock.delete", { product: favourite.id }))
        } else {
            await axios.post(route("retina.models.remind_back_in_stock.store", { product: favourite.id }))
        }
        favourite.has_reminder = !favourite.has_reminder
        notify({ title: favourite.has_reminder ? ctrans("We will email you when it is back in stock") : ctrans("Reminder removed"), type: "success" })
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message || ctrans("Please try again"),
            type: "error",
        })
    } finally {
        togglingReminderIds.value = togglingReminderIds.value.filter((id) => id !== favourite.id)
    }
}

const recommendationsBreakpoints = {
    0: { slidesPerView: 1.6, slidesPerGroup: 1, spaceBetween: 12 },
    480: { slidesPerView: 2.3, slidesPerGroup: 2, spaceBetween: 16 },
    760: { slidesPerView: 4, slidesPerGroup: 3, spaceBetween: 20 },
    1000: { slidesPerView: 4, slidesPerGroup: 4, spaceBetween: 24 },
    1300: { slidesPerView: 5, slidesPerGroup: 5, spaceBetween: 24 },
}

const recommendationsSwiper = ref<SwiperInstance | null>(null)
const isRecommendationsSwipeable = ref(false)

const syncRecommendationsNavigation = (swiper: SwiperInstance) => {
    isRecommendationsSwipeable.value = !swiper.isLocked
}

const onRecommendationsSwiperReady = (swiper: SwiperInstance) => {
    recommendationsSwiper.value = swiper
    syncRecommendationsNavigation(swiper)
}

const togglingFavouriteIds = ref<number[]>([])

const toggleRecommendationFavourite = async (product: { id: number, is_favourite: boolean }) => {
    if (props.readOnly || togglingFavouriteIds.value.includes(product.id)) return
    togglingFavouriteIds.value.push(product.id)
    try {
        if (product.is_favourite) {
            await axios.delete(route("retina.models.product.unfavourite", { product: product.id }))
        } else {
            await axios.post(route("retina.models.product.favourite", { product: product.id }))
        }
        product.is_favourite = !product.is_favourite
        layout?.reload_handle?.()
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message || ctrans("Please try again"),
            type: "error",
        })
    } finally {
        togglingFavouriteIds.value = togglingFavouriteIds.value.filter((id) => id !== product.id)
    }
}

const toggleRecommendationBackInStock = async (product: { id: number, is_back_in_stock: boolean }) => {
    const reminder = { id: product.id, has_reminder: product.is_back_in_stock }
    await toggleReminder(reminder)
    product.is_back_in_stock = reminder.has_reminder
}

const repeatingOrderId = ref<number | null>(null)

const repeatOrder = async (order: RecentOrder) => {
    if (props.readOnly) return
    repeatingOrderId.value = order.id
    try {
        const { data } = await axios.post(route("retina.models.order.repeat", { order: order.id }))
        notify({
            title: data.added === 1 ? ctrans("1 product added to your basket") : ctrans(":count products added to your basket", { count: String(data.added) }),
            text: data.skipped.length
                ? ctrans("Not available right now: :products", { products: data.skipped.map((p: { code: string }) => p.code).join(", ") })
                : undefined,
            type: data.added ? "success" : "warning",
        })
        if (data.added) {
            router.visit(route("retina.ecom.basket.show"))
        }
    } catch (error: any) {
        notify({
            title: ctrans("Could not repeat this order"),
            text: error?.response?.data?.message || ctrans("Please try again"),
            type: "error",
        })
    } finally {
        repeatingOrderId.value = null
    }
}

const voucherBenefit = (voucher: Voucher) => {
    const amount = voucher.min_amount ? money(voucher.min_amount) : null
    if (voucher.is_free_shipping) {
        return amount ? ctrans("Free shipping on orders over :amount", { amount }) : ctrans("Free shipping on your order")
    }
    if (voucher.is_gift) {
        return amount ? ctrans("A free gift with orders over :amount", { amount }) : ctrans("A free gift with your order")
    }
    const discount = voucher.percentage_off ? percent(voucher.percentage_off) : (voucher.amount_off ? money(voucher.amount_off) : null)
    if (!discount) {
        return amount ? ctrans("For orders over :amount", { amount }) : ""
    }
    if (voucher.is_whole_order) {
        return amount ? ctrans(":discount off orders over :amount", { discount, amount }) : ctrans(":discount off your order", { discount })
    }
    return amount ? ctrans(":discount off selected products on orders over :amount", { discount, amount }) : ctrans(":discount off selected products", { discount })
}

const goldRewardDaysLeft = (daysLeft: number) => {
    if (daysLeft <= 0) return ctrans("Last day today")
    if (daysLeft === 1) return ctrans("1 day left")
    return ctrans(":count days left", { count: String(daysLeft) })
}

const copiedVoucherCode = ref<string | null>(null)

const copyVoucherCode = async (code: string) => {
    try {
        await navigator.clipboard.writeText(code)
        copiedVoucherCode.value = code
        setTimeout(() => { if (copiedVoucherCode.value === code) copiedVoucherCode.value = null }, 2000)
    } catch {
        notify({ title: ctrans("Could not copy, the code is :code", { code }), type: "warning" })
    }
}

const orderStateChip = (state: string) => {
    if (["dispatched", "finalised"].includes(state)) return "bg-emerald-50 text-emerald-800 ring-emerald-600/20"
    if (state === "cancelled") return "bg-gray-100 text-gray-600 ring-gray-500/20"
    return "bg-blue-50 text-blue-700 ring-blue-600/20"
}

const recommendationsTitle = computed(() => props.insights.recommendations_source === "shop_best_sellers"
    ? ctrans("Popular with other shops")
    : (isLapsed.value ? ctrans("New for you since your last order") : ctrans("Suggested for your shop")))

const recommendationsSubtitle = computed(() => props.insights.recommendations_source === "shop_best_sellers"
    ? ctrans("What other shops are ordering most at the moment, a good place to start.")
    : ctrans("Products that sell well alongside the ones you order, and new to you."))

const overview = computed(() => {
    const k = props.insights.kpis
    const recent = k.orders > 0
    return [
        {
            label: isLapsed.value ? ctrans("Previous order frequency") : ctrans("Order frequency"),
            value: k.order_every_days ? (k.order_every_days === 1 ? ctrans("Every day") : ctrans("Every :count days", { count: String(k.order_every_days) })) : "—",
            hint: k.order_every_days ? ctrans("Average gap between orders") : (hasHistory.value ? ctrans("Shown after a few more orders") : ""),
        },
        {
            label: ctrans("Average order value"),
            value: k.average_order ? money(k.average_order) : "—",
            hint: k.average_order ? (recent ? ctrans("Excl. VAT · last 12 months") : ctrans("Excl. VAT · all orders")) : "",
        },
        {
            label: ctrans("Total orders"),
            value: k.total_orders.toLocaleString(languageCode.value),
            hint: ctrans("Since joining"),
        },
    ]
})
</script>

<template>
    <div class="space-y-6 text-gray-700">
        <header>
            <h2 class="text-2xl font-semibold tracking-tight text-gray-900">{{ heading.title }}</h2>
            <p class="mt-1 text-gray-500">{{ heading.subtitle }}</p>
        </header>

        <div v-if="isLapsed && lastOrder" class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:flex-row sm:items-center">
            <p class="flex flex-1 items-center gap-3 text-sm font-medium text-gray-700">
                <FontAwesomeIcon :icon="faRedoAlt" class="text-gray-500" fixed-width aria-hidden="true" />
                {{ ctrans("Pick up where you left off: your last order was :reference on :date.", { reference: lastOrder.reference, date: shortDate(lastOrder.date) }) }}
            </p>
            <Button
                :label="ctrans('Repeat this order')"
                :icon="faRedoAlt"
                :loading="repeatingOrderId === lastOrder.id"
                :disabled="readOnly"
                @click="repeatOrder(lastOrder)"
            />
        </div>
        <div v-else-if="insights.gold_reward" class="flex flex-col gap-1 rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm sm:flex-row sm:items-center sm:gap-3">
            <p class="flex items-center gap-3 font-semibold text-gray-900">
                <FontAwesomeIcon :icon="faGift" class="text-yellow-600" fixed-width aria-hidden="true" />
                {{ insights.gold_reward.label }}
            </p>
            <p class="text-gray-600 sm:before:mr-3 sm:before:content-['·']">
                {{ goldRewardDaysLeft(insights.gold_reward.days_left) }} · {{ ctrans("Expires :date", { date: longDate(insights.gold_reward.expires_at) }) }}
            </p>
        </div>

        <ul v-if="insights.vouchers?.length" class="divide-y divide-gray-200 rounded-lg border border-dashed border-gray-400">
            <li v-for="voucher in insights.vouchers" :key="voucher.code" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3">
                <FontAwesomeIcon :icon="faTicketAlt" class="text-gray-500" fixed-width aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-900">{{ voucher.name }}</p>
                    <p v-if="voucherBenefit(voucher)" class="text-sm text-gray-600">{{ voucherBenefit(voucher) }}</p>
                    <p v-if="voucher.expires_at" class="text-xs text-gray-500">{{ ctrans("Ends :date", { date: shortDate(voucher.expires_at) }) }}</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 font-mono text-sm font-semibold tracking-wide text-gray-700 hover:bg-gray-100"
                    :aria-label="ctrans('Copy voucher code :code', { code: voucher.code })"
                    @click="copyVoucherCode(voucher.code)"
                >
                    {{ voucher.code }}
                    <FontAwesomeIcon :icon="copiedVoucherCode === voucher.code ? faCheck : faCopy" fixed-width aria-hidden="true" />
                </button>
                <span class="sr-only" aria-live="polite">{{ copiedVoucherCode === voucher.code ? ctrans("Copied") : "" }}</span>
            </li>
        </ul>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <section v-if="orderAgainRows.length" class="overflow-hidden rounded-lg border border-gray-200 lg:col-span-2">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-2">
                    <h3 class="text-base font-semibold text-gray-900">{{ isLapsed ? ctrans("Review and order again") : ctrans("Order again") }}</h3>
                    <p class="text-xs text-gray-500">{{ ctrans("Your most ordered products. Check the quantity and add them to your basket.") }}</p>
                </div>

                <div class="px-4 pb-4">
                    <Select
                        v-if="pickerOptions.length"
                        :modelValue="null"
                        :options="pickerOptions"
                        optionValue="id"
                        optionLabel="name"
                        filter
                        :filterFields="['code', 'name']"
                        :placeholder="ctrans('Find a product you have ordered before')"
                        :disabled="readOnly"
                        class="mt-3 w-full"
                        @update:modelValue="pickProduct"
                    >
                        <template #option="{ option }">
                            <span class="truncate">{{ option.name }}</span>
                            <span class="ml-2 flex-none text-xs text-gray-500">{{ option.code }}</span>
                        </template>
                    </Select>

                    <ul class="mt-3 divide-y divide-gray-100">
                        <li v-for="regular in orderAgainRows" :key="regular.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 sm:flex-nowrap">
                            <div class="h-14 w-14 flex-none overflow-hidden rounded-md border border-gray-200 bg-white" :class="{ 'opacity-60': regular.stock_status === 'out_of_stock' }">
                                <Image v-if="regular.image" :src="regular.image" :alt="regular.name" class="h-full w-full object-contain" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <a v-if="regular.url && !readOnly" :href="regular.url" class="block truncate text-sm font-semibold text-gray-900 hover:underline">{{ regular.name }}</a>
                                <span v-else class="block truncate text-sm font-semibold text-gray-900">{{ regular.name }}</span>
                                <p class="text-xs text-gray-500">
                                    {{ regular.code }} · {{ packLine(regular.price, regular.units, regular.unit) }}
                                </p>
                                <p class="text-xs" :class="regular.stock_status === 'low' ? 'font-medium text-amber-700' : 'text-gray-500'">{{ rowHint(regular) }}</p>
                            </div>
                            <span class="hidden whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset md:inline-flex" :class="stockChip(regular.stock_status).class">
                                {{ stockChip(regular.stock_status).label }}
                            </span>

                            <div v-if="inBasket(regular)" class="flex w-full items-center justify-end gap-1 whitespace-nowrap text-sm font-medium text-green-600 sm:w-auto">
                                <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                                {{ ctrans(":count in basket", { count: String(inBasket(regular)) }) }}
                            </div>
                            <div v-else-if="canAdd(regular)" class="flex w-full items-center justify-end gap-2 sm:w-auto">
                                <div class="flex items-center rounded-md border border-gray-300 bg-white focus-within:ring-2 focus-within:ring-gray-400">
                                    <button
                                        type="button"
                                        class="px-2.5 py-2 text-gray-500 hover:text-gray-900 disabled:opacity-40"
                                        :aria-label="ctrans('Decrease quantity')"
                                        :disabled="readOnly"
                                        @click="stepQuantity(regular.id, suggestedQuantity(regular), -1, maxQuantity(regular))"
                                    >
                                        <FontAwesomeIcon :icon="faMinus" fixed-width aria-hidden="true" />
                                    </button>
                                    <input
                                        type="number"
                                        min="1"
                                        :max="maxQuantity(regular) || undefined"
                                        :value="quantityFor(regular.id, suggestedQuantity(regular))"
                                        :aria-label="ctrans('Quantity for :product', { product: regular.name })"
                                        :disabled="readOnly"
                                        class="w-12 border-0 p-0 text-center text-sm tabular-nums focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                                        @change="setQuantity(regular.id, $event.target as HTMLInputElement, maxQuantity(regular))"
                                    />
                                    <button
                                        type="button"
                                        class="px-2.5 py-2 text-gray-500 hover:text-gray-900 disabled:opacity-40"
                                        :aria-label="ctrans('Increase quantity')"
                                        :disabled="readOnly"
                                        @click="stepQuantity(regular.id, suggestedQuantity(regular), 1, maxQuantity(regular))"
                                    >
                                        <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
                                    </button>
                                </div>
                                <Button
                                    :label="ctrans('Add')"
                                    :icon="faShoppingBasket"
                                    :loading="addingProductIds.includes(regular.id)"
                                    :disabled="readOnly"
                                    @click="addToBasket(regular, quantityFor(regular.id, suggestedQuantity(regular)))"
                                />
                            </div>
                            <Button
                                v-else-if="regular.stock_status === 'out_of_stock'"
                                type="tertiary"
                                size="xs"
                                :label="regular.has_reminder ? ctrans('Notifying you') : ctrans('Notify me')"
                                :icon="regular.has_reminder ? fasBell : faBell"
                                :aria-pressed="regular.has_reminder"
                                :loading="togglingReminderIds.includes(regular.id)"
                                :disabled="readOnly"
                                @click="toggleReminder(regular)"
                            />
                            <span v-else class="w-full text-right text-xs text-gray-500 sm:w-auto">{{ ctrans("Not available to order") }}</span>
                        </li>
                    </ul>

                    <Link v-if="!readOnly" :href="route('retina.ecom.interest.previously_ordered.index')" class="mt-2 inline-flex items-center gap-2 text-sm text-gray-500 underline-offset-4 hover:text-gray-700 hover:underline">
                        {{ ctrans("View all purchased products") }}
                        <FontAwesomeIcon :icon="faArrowRight" fixed-width aria-hidden="true" />
                    </Link>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200" :class="{ 'lg:col-span-3': !orderAgainRows.length }">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-2">
                    <h3 class="text-base font-semibold text-gray-900">{{ ctrans("Favourites and watchlist") }}</h3>
                    <p v-if="insights.favourites.length" class="text-xs text-gray-500">{{ ctrans("Your saved products.") }}</p>
                </div>
                <div v-if="insights.favourites.length" class="px-4 pb-4">
                    <ul class="divide-y divide-gray-100">
                        <li v-for="favourite in insights.favourites" :key="favourite.id" class="flex items-center gap-3 py-3">
                            <div class="h-12 w-12 flex-none overflow-hidden rounded-md border border-gray-200 bg-white">
                                <Image v-if="favourite.image" :src="favourite.image" :alt="favourite.name" class="h-full w-full object-contain" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <a v-if="favourite.url && !readOnly" :href="favourite.url" class="block truncate text-sm font-semibold text-gray-900 hover:underline">{{ favourite.name }}</a>
                                <span v-else class="block truncate text-sm font-semibold text-gray-900">{{ favourite.name }}</span>
                                <span class="mt-1 inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="stockChip(favourite.stock_status).class">
                                    {{ stockChip(favourite.stock_status).label }}
                                </span>
                            </div>
                            <span v-if="inBasket(favourite)" class="flex flex-none items-center gap-1 whitespace-nowrap text-xs font-medium text-green-600">
                                <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                                {{ ctrans(":count in basket", { count: String(inBasket(favourite)) }) }}
                            </span>
                            <Button
                                v-else-if="canAdd(favourite)"
                                :icon="faShoppingBasket"
                                :tooltip="ctrans('Add to basket')"
                                :loading="addingProductIds.includes(favourite.id)"
                                :disabled="readOnly"
                                @click="addToBasket(favourite, 1)"
                            />
                            <Button
                                v-else-if="favourite.stock_status === 'out_of_stock'"
                                type="tertiary"
                                size="xs"
                                :label="favourite.has_reminder ? ctrans('Notifying you') : ctrans('Notify me')"
                                :icon="favourite.has_reminder ? fasBell : faBell"
                                :aria-pressed="favourite.has_reminder"
                                :loading="togglingReminderIds.includes(favourite.id)"
                                :disabled="readOnly"
                                @click="toggleReminder(favourite)"
                            />
                        </li>
                    </ul>
                    <Link v-if="!readOnly" :href="route('retina.ecom.interest.favourites.index')" class="mt-2 inline-flex items-center gap-2 text-sm text-gray-500 underline-offset-4 hover:text-gray-700 hover:underline">
                        {{ ctrans("All favourites") }}
                        <FontAwesomeIcon :icon="faArrowRight" fixed-width aria-hidden="true" />
                    </Link>
                </div>
                <div v-else class="flex gap-3 p-4">
                    <FontAwesomeIcon :icon="faHeart" class="mt-0.5 text-xl text-gray-400" fixed-width aria-hidden="true" />
                    <p class="text-sm text-gray-500">
                        {{ ctrans("Tap the heart on any product to save it here and follow its stock.") }}
                    </p>
                </div>
            </section>
        </div>

        <section v-if="insights.recent_orders.length" class="overflow-hidden rounded-lg border border-gray-200">
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 bg-gray-50 px-4 py-2">
                <h3 class="text-base font-semibold text-gray-900">{{ ctrans("Recent orders") }}</h3>
                <Link v-if="!readOnly" :href="route('retina.ecom.orders.index')" class="inline-flex items-center gap-2 text-sm text-gray-500 underline-offset-4 hover:text-gray-700 hover:underline">
                    {{ ctrans("All orders") }}
                    <FontAwesomeIcon :icon="faArrowRight" fixed-width aria-hidden="true" />
                </Link>
            </div>
            <ul class="divide-y divide-gray-100 px-4">
                <li v-for="order in insights.recent_orders.slice(0, 3)" :key="order.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        <span v-if="readOnly" class="font-semibold text-gray-900">{{ order.reference }}</span>
                        <Link v-else :href="route('retina.ecom.orders.show', { order: order.slug })" class="font-semibold text-gray-900 hover:underline">{{ order.reference }}</Link>
                        <span class="text-gray-500">· {{ shortDate(order.date) }} · {{ order.items === 1 ? ctrans("1 item") : ctrans(":count items", { count: String(order.items) }) }} · {{ money(order.total) }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="orderStateChip(order.state)">{{ order.state_label }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="order.invoice && !readOnly" :href="route('retina.ecom.invoices.show', [order.invoice])">
                            <Button type="tertiary" size="xs" :label="ctrans('Invoice')" :icon="faFileInvoice" />
                        </Link>
                        <Button
                            type="tertiary"
                            size="xs"
                            :label="ctrans('Order again')"
                            :icon="faRedoAlt"
                            :loading="repeatingOrderId === order.id"
                            :disabled="readOnly"
                            @click="repeatOrder(order)"
                        />
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="insights.recommendations.length" id="recommendations">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">{{ recommendationsTitle }}</h3>
                    <p class="text-sm text-gray-500">{{ recommendationsSubtitle }}</p>
                </div>
                <div v-if="!readOnly && isRecommendationsSwipeable" class="hidden flex-none gap-2 sm:flex">
                    <Button
                        type="tertiary"
                        size="xs"
                        :icon="faChevronLeft"
                        :tooltip="ctrans('Previous')"
                        @click="recommendationsSwiper?.slidePrev()"
                    />
                    <Button
                        type="tertiary"
                        size="xs"
                        :icon="faChevronRight"
                        :tooltip="ctrans('Next')"
                        @click="recommendationsSwiper?.slideNext()"
                    />
                </div>
            </div>
            <Swiper
                v-if="!readOnly"
                :breakpoints="recommendationsBreakpoints"
                breakpointsBase="container"
                :loop="true"
                class="mt-4 w-full"
                @swiper="onRecommendationsSwiperReady"
                @lock="syncRecommendationsNavigation"
                @unlock="syncRecommendationsNavigation"
                @breakpoint="syncRecommendationsNavigation"
            >
                <SwiperSlide v-for="product in insights.recommendations" :key="product.id" class="offers !h-auto">
                    <ProductCardEcom3
                        :product="product"
                        :hasInBasket="product"
                        :basketButton="true"
                        :isLoadingFavourite="togglingFavouriteIds.includes(product.id)"
                        :isLoadingRemindBackInStock="togglingReminderIds.includes(product.id)"
                        :addToBasketRoute="{ name: 'retina.models.product.add-to-basket', method: 'post' }"
                        :updateBasketQuantityRoute="{ name: 'retina.models.transaction.update', method: 'patch' }"
                        :routeGettransactionProductData="{ name: 'retina.json.basket_transaction_product_data' }"
                        @setFavorite="toggleRecommendationFavourite"
                        @unsetFavorite="toggleRecommendationFavourite"
                        @setBackInStock="toggleRecommendationBackInStock"
                        @unsetBackInStock="toggleRecommendationBackInStock"
                    />
                </SwiperSlide>
            </Swiper>
            <div v-else class="mt-4 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-5">
                <div v-for="product in insights.recommendations.slice(0, 5)" :key="product.id" class="group pointer-events-none flex flex-col">
                    <a class="flex h-28 items-center justify-center overflow-hidden rounded bg-white">
                        <Image
                            v-if="product.web_images?.main"
                            :src="product.web_images.main.gallery ?? product.web_images.main.thumbnail ?? product.web_images.main.original"
                            :alt="product.name"
                            class="flex h-full w-full justify-center transition group-hover:scale-105"
                        />
                    </a>
                    <span class="mt-2 line-clamp-2 text-sm font-semibold text-gray-900">{{ product.name }}</span>
                    <p class="text-xs text-gray-500">{{ product.code }}</p>
                    <div class="mt-auto flex items-center justify-between gap-2 pt-2">
                        <p class="text-xs tabular-nums text-gray-700">{{ packLine(product.discounted_price ?? product.price, product.units, product.unit) }}</p>
                        <span v-if="inBasket(product)" class="flex flex-none items-center gap-1 whitespace-nowrap text-xs font-medium text-green-600">
                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                            {{ ctrans(":count in basket", { count: String(inBasket(product)) }) }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-2">
                <h3 class="text-base font-semibold text-gray-900">{{ ctrans("Your order overview") }}</h3>
                <p v-if="!hasHistory" class="text-xs text-gray-500">{{ ctrans("Your summary will appear after your first order.") }}</p>
            </div>
            <dl class="grid grid-cols-1 divide-y divide-gray-200 text-center sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div v-for="stat in overview" :key="stat.label" class="px-4 py-4">
                    <dt class="text-sm text-gray-500">{{ stat.label }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ stat.value }}</dd>
                    <dd v-if="stat.hint" class="mt-1 text-xs text-gray-400">{{ stat.hint }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>

<style scoped lang="scss">
:deep(.discount .background-primary) {
    background-color: v-bind("layout?.iris?.theme?.color?.[4]") !important;
}

:deep(.discount .text-primary) {
    color: v-bind("layout?.iris?.theme?.color?.[4]") !important;
}

:deep(.discount .offer-trigger-label) {
    @apply bg-gray-50 border border-b-4 rounded-md px-2 py-1 leading-3 text-xxs md:text-xs;
    color: #E87928 !important;
    border-color: #E87928 !important;
}
</style>
