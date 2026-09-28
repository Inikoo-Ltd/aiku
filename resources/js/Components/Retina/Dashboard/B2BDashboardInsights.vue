<script setup lang="ts">
import { computed, inject, reactive, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRedoAlt, faShoppingBasket, faGift, faBell, faBellSlash, faHeart, faFileInvoice, faArrowRight, faCheck, faMinus, faPlus } from "@fal"
import Image from "@common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faRedoAlt, faShoppingBasket, faGift, faBell, faBellSlash, faHeart, faFileInvoice, faArrowRight, faCheck, faMinus, faPlus)

type StockStatus = "in_stock" | "low" | "out_of_stock" | "unavailable"

interface Regular {
    id: number
    code: string
    name: string
    image: Record<string, string> | null
    url: string | null
    price: number
    unit: string | null
    available_quantity: number
    stock_status: StockStatus
    eta: string | null
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

const shortDate = (iso: string | null) => {
    if (!iso) return ""
    return new Intl.DateTimeFormat(languageCode.value, { day: "numeric", month: "short" }).format(new Date(iso))
}

const longDate = (iso: string) => new Intl.DateTimeFormat(languageCode.value, { day: "numeric", month: "long", year: "numeric" }).format(new Date(iso))

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

const orderAgainRows = computed(() => {
    const limit = props.insights.kpis.orders >= HEAVY_BUYER_ORDERS ? 10 : 5
    return props.insights.regulars
        .filter((regular) => regular.stock_status !== "unavailable")
        .map((regular, index) => ({ regular, index }))
        .sort((a, b) => orderAgainSortRank(a.regular) - orderAgainSortRank(b.regular) || a.index - b.index)
        .slice(0, limit)
        .map(({ regular }) => regular)
})

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

const setQuantity = (id: number, value: string, max?: number) => {
    const parsed = Math.max(1, Math.floor(Number(value) || 1))
    quantities[id] = max && max > 0 ? Math.min(parsed, max) : parsed
}

const rowHint = (regular: Regular) => {
    if (regular.stock_status === "out_of_stock") {
        return regular.eta ? ctrans("Back :date", { date: shortDate(regular.eta) }) : ctrans("Arrival date to be confirmed")
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
            return { label: ctrans("No longer available"), class: "bg-stone-100 text-stone-600 ring-stone-500/20" }
        default:
            return { label: ctrans("In stock"), class: "bg-emerald-50 text-emerald-800 ring-emerald-600/20" }
    }
}

const addingProductIds = ref<number[]>([])

const addToBasket = async (productId: number, quantityInBasket: number, quantity: number) => {
    if (props.readOnly) return
    addingProductIds.value.push(productId)
    try {
        await axios.post(route("retina.models.product.add-to-basket", { product: productId }), {
            quantity: quantityInBasket + quantity,
        })
        notify({ title: ctrans("Added to basket"), type: "success" })
        delete quantities[productId]
        layout?.reload_handle?.()
        router.reload({ only: ["insights"] })
    } catch (error: any) {
        notify({
            title: ctrans("Could not add to basket"),
            text: error?.response?.data?.message || ctrans("Please try again"),
            type: "error",
        })
    } finally {
        addingProductIds.value = addingProductIds.value.filter((id) => id !== productId)
    }
}

const togglingReminderIds = ref<number[]>([])

const toggleReminder = async (favourite: Favourite) => {
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

const repeatingOrderId = ref<number | null>(null)

const repeatOrder = async (order: RecentOrder) => {
    if (props.readOnly) return
    repeatingOrderId.value = order.id
    try {
        const { data } = await axios.post(route("retina.models.order.repeat", { order: order.id }))
        notify({
            title: ctrans(":count products added to your basket", { count: String(data.added) }),
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

const orderStateChip = (state: string) => {
    if (["dispatched", "finalised"].includes(state)) return "bg-emerald-50 text-emerald-800 ring-emerald-600/20"
    if (state === "cancelled") return "bg-stone-100 text-stone-600 ring-stone-500/20"
    return "bg-[#f8efe4] text-[#7a4f33] ring-[#a0694a]/20"
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
            value: k.order_every_days ? ctrans("Every :count days", { count: String(k.order_every_days) }) : "—",
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
    <div class="space-y-8 font-['Raleway',_ui-sans-serif,_system-ui,_sans-serif] text-stone-800">
        <header>
            <h2 class="text-2xl font-bold tracking-tight text-stone-900 sm:text-3xl">{{ heading.title }}</h2>
            <p class="mt-1 text-stone-600">{{ heading.subtitle }}</p>
        </header>

        <div v-if="isLapsed && lastOrder" class="flex flex-col gap-3 rounded-lg bg-[#f8efe4] px-4 py-3 sm:flex-row sm:items-center">
            <p class="flex flex-1 items-center gap-3 font-medium text-[#7a4f33]">
                <FontAwesomeIcon :icon="faRedoAlt" class="text-[#a0694a]" fixed-width aria-hidden="true" />
                {{ ctrans("Pick up where you left off: your last order was :reference on :date.", { reference: lastOrder.reference, date: shortDate(lastOrder.date) }) }}
            </p>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-[#a0694a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#8a5a3e] disabled:opacity-50"
                :disabled="readOnly || repeatingOrderId === lastOrder.id"
                @click="repeatOrder(lastOrder)"
            >
                <FontAwesomeIcon :icon="faRedoAlt" fixed-width aria-hidden="true" :spin="repeatingOrderId === lastOrder.id" />
                {{ ctrans("Repeat this order") }}
            </button>
        </div>
        <div v-else-if="insights.gold_reward" class="flex flex-col gap-1 rounded-lg bg-[#f8efe4] px-4 py-3 sm:flex-row sm:items-center sm:gap-3">
            <p class="flex items-center gap-3 font-semibold text-[#7a4f33]">
                <FontAwesomeIcon :icon="faGift" class="text-[#a0694a]" fixed-width aria-hidden="true" />
                {{ insights.gold_reward.label }}
            </p>
            <p class="text-[#7a4f33] sm:before:mr-3 sm:before:content-['·']">
                {{ ctrans(":count days left", { count: String(insights.gold_reward.days_left) }) }} · {{ ctrans("Expires :date", { date: longDate(insights.gold_reward.expires_at) }) }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 lg:divide-x lg:divide-stone-200">
            <section v-if="orderAgainRows.length" class="lg:col-span-2">
                <h3 class="text-xl font-bold text-stone-900">{{ isLapsed ? ctrans("Review and order again") : ctrans("Order again") }}</h3>
                <p class="text-sm text-stone-500">{{ ctrans("Your most ordered products. Check the quantity and add them to your basket.") }}</p>

                <ul class="mt-3 divide-y divide-stone-200">
                    <li v-for="regular in orderAgainRows" :key="regular.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 sm:flex-nowrap">
                        <div class="h-14 w-14 flex-none overflow-hidden rounded-md bg-stone-50" :class="{ 'opacity-60': regular.stock_status === 'out_of_stock' }">
                            <Image v-if="regular.image" :src="regular.image" :alt="regular.name" class="h-full w-full object-contain" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <a v-if="regular.url && !readOnly" :href="regular.url" class="block truncate font-medium text-stone-900 hover:underline">{{ regular.name }}</a>
                            <span v-else class="block truncate font-medium text-stone-900">{{ regular.name }}</span>
                            <p class="text-xs text-stone-500">
                                {{ regular.code }} · {{ money(regular.price) }}
                            </p>
                            <p class="text-xs" :class="regular.stock_status === 'low' ? 'font-medium text-amber-800' : 'text-stone-500'">{{ rowHint(regular) }}</p>
                        </div>
                        <span class="hidden whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset md:inline-flex" :class="stockChip(regular.stock_status).class">
                            {{ stockChip(regular.stock_status).label }}
                        </span>

                        <div v-if="regular.quantity_in_basket" class="flex w-full items-center justify-end gap-1 whitespace-nowrap text-sm font-medium text-emerald-800 sm:w-auto">
                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                            {{ ctrans(":count in basket", { count: String(regular.quantity_in_basket) }) }}
                        </div>
                        <div v-else-if="canAdd(regular)" class="flex w-full items-center justify-end gap-2 sm:w-auto">
                            <div class="flex items-center rounded-md border border-stone-300">
                                <button
                                    type="button"
                                    class="px-2 py-1.5 text-stone-600 hover:text-stone-900 disabled:opacity-40"
                                    :aria-label="ctrans('Decrease quantity')"
                                    :disabled="readOnly"
                                    @click="stepQuantity(regular.id, suggestedQuantity(regular), -1, regular.available_quantity)"
                                >
                                    <FontAwesomeIcon :icon="faMinus" fixed-width aria-hidden="true" />
                                </button>
                                <input
                                    type="number"
                                    min="1"
                                    :max="regular.available_quantity || undefined"
                                    :value="quantityFor(regular.id, suggestedQuantity(regular))"
                                    :aria-label="ctrans('Quantity for :product', { product: regular.name })"
                                    :disabled="readOnly"
                                    class="w-12 border-0 p-0 text-center text-sm tabular-nums focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                                    @change="setQuantity(regular.id, ($event.target as HTMLInputElement).value, regular.available_quantity)"
                                />
                                <button
                                    type="button"
                                    class="px-2 py-1.5 text-stone-600 hover:text-stone-900 disabled:opacity-40"
                                    :aria-label="ctrans('Increase quantity')"
                                    :disabled="readOnly"
                                    @click="stepQuantity(regular.id, suggestedQuantity(regular), 1, regular.available_quantity)"
                                >
                                    <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
                                </button>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-md bg-[#a0694a] px-3 py-1.5 text-sm font-semibold text-white hover:bg-[#8a5a3e] disabled:opacity-50"
                                :disabled="readOnly || addingProductIds.includes(regular.id)"
                                @click="addToBasket(regular.id, 0, quantityFor(regular.id, suggestedQuantity(regular)))"
                            >
                                <FontAwesomeIcon :icon="faShoppingBasket" fixed-width aria-hidden="true" />
                                {{ ctrans("Add") }}
                            </button>
                        </div>
                    </li>
                </ul>

                <Link v-if="!readOnly" :href="route('retina.ecom.interest.previously_ordered.index')" class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-[#8a5a3e] underline-offset-4 hover:underline">
                    {{ ctrans("View all purchased products") }}
                    <FontAwesomeIcon :icon="faArrowRight" fixed-width aria-hidden="true" />
                </Link>
            </section>

            <section :class="orderAgainRows.length ? 'lg:pl-8' : 'lg:col-span-3'">
                <h3 class="text-xl font-bold text-stone-900">{{ ctrans("Favourites and watchlist") }}</h3>
                <template v-if="insights.favourites.length">
                    <p class="text-sm text-stone-500">{{ ctrans("Your saved products.") }}</p>
                    <ul class="mt-3 divide-y divide-stone-200">
                        <li v-for="favourite in insights.favourites" :key="favourite.id" class="flex items-center gap-3 py-3">
                            <div class="h-12 w-12 flex-none overflow-hidden rounded-md bg-stone-50">
                                <Image v-if="favourite.image" :src="favourite.image" :alt="favourite.name" class="h-full w-full object-contain" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <a v-if="favourite.url && !readOnly" :href="favourite.url" class="block truncate text-sm font-medium text-stone-900 hover:underline">{{ favourite.name }}</a>
                                <span v-else class="block truncate text-sm font-medium text-stone-900">{{ favourite.name }}</span>
                                <span class="mt-1 inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="stockChip(favourite.stock_status).class">
                                    {{ stockChip(favourite.stock_status).label }}
                                </span>
                            </div>
                            <span v-if="favourite.quantity_in_basket" class="flex-none text-emerald-800" v-tooltip="ctrans(':count in basket', { count: String(favourite.quantity_in_basket) })">
                                <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                                <span class="sr-only">{{ ctrans(":count in basket", { count: String(favourite.quantity_in_basket) }) }}</span>
                            </span>
                            <button
                                v-else-if="canAdd(favourite)"
                                type="button"
                                class="flex-none rounded-md bg-[#a0694a] px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-[#8a5a3e] disabled:opacity-50"
                                :aria-label="ctrans('Add to basket')"
                                :disabled="readOnly || addingProductIds.includes(favourite.id)"
                                @click="addToBasket(favourite.id, 0, 1)"
                            >
                                <FontAwesomeIcon :icon="faShoppingBasket" fixed-width aria-hidden="true" />
                            </button>
                            <button
                                v-else-if="favourite.stock_status === 'out_of_stock'"
                                type="button"
                                class="inline-flex flex-none items-center gap-1.5 rounded-md border border-[#a0694a] px-2.5 py-1.5 text-xs font-semibold text-[#8a5a3e] hover:bg-[#f8efe4] disabled:opacity-50"
                                :disabled="readOnly || togglingReminderIds.includes(favourite.id)"
                                @click="toggleReminder(favourite)"
                            >
                                <FontAwesomeIcon :icon="favourite.has_reminder ? faBellSlash : faBell" fixed-width aria-hidden="true" />
                                {{ favourite.has_reminder ? ctrans("Notifying") : ctrans("Notify me") }}
                            </button>
                        </li>
                    </ul>
                    <Link v-if="!readOnly" :href="route('retina.ecom.interest.favourites.index')" class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-[#8a5a3e] underline-offset-4 hover:underline">
                        {{ ctrans("All favourites") }}
                        <FontAwesomeIcon :icon="faArrowRight" fixed-width aria-hidden="true" />
                    </Link>
                </template>
                <div v-else class="mt-3 flex gap-3">
                    <FontAwesomeIcon :icon="faHeart" class="mt-0.5 text-xl text-[#a0694a]" fixed-width aria-hidden="true" />
                    <p class="text-sm text-stone-600">
                        {{ ctrans("Tap the heart on any product to save it here and follow its stock.") }}
                    </p>
                </div>
            </section>
        </div>

        <section v-if="insights.recent_orders.length" class="border-t border-stone-200 pt-6">
            <div class="flex items-baseline justify-between gap-4">
                <h3 class="text-xl font-bold text-stone-900">{{ ctrans("Recent orders") }}</h3>
                <Link v-if="!readOnly" :href="route('retina.ecom.orders.index')" class="inline-flex items-center gap-2 text-sm font-medium text-[#8a5a3e] underline-offset-4 hover:underline">
                    {{ ctrans("All orders") }}
                    <FontAwesomeIcon :icon="faArrowRight" fixed-width aria-hidden="true" />
                </Link>
            </div>
            <ul class="mt-2 divide-y divide-stone-200">
                <li v-for="order in insights.recent_orders.slice(0, 3)" :key="order.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        <span v-if="readOnly" class="font-semibold text-stone-900">{{ order.reference }}</span>
                        <Link v-else :href="route('retina.ecom.orders.show', { order: order.slug })" class="font-semibold text-stone-900 hover:underline">{{ order.reference }}</Link>
                        <span class="text-stone-500">· {{ shortDate(order.date) }} · {{ order.items === 1 ? ctrans("1 item") : ctrans(":count items", { count: String(order.items) }) }} · {{ money(order.total) }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="orderStateChip(order.state)">{{ order.state_label }}</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm font-medium">
                        <Link v-if="order.invoice && !readOnly" :href="route('retina.ecom.invoices.show', [order.invoice])" class="inline-flex items-center gap-1.5 text-[#8a5a3e] underline-offset-4 hover:underline">
                            <FontAwesomeIcon :icon="faFileInvoice" fixed-width aria-hidden="true" />
                            {{ ctrans("Invoice") }}
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 text-[#8a5a3e] underline-offset-4 hover:underline disabled:opacity-50"
                            :disabled="readOnly || repeatingOrderId === order.id"
                            @click="repeatOrder(order)"
                        >
                            <FontAwesomeIcon :icon="faRedoAlt" fixed-width aria-hidden="true" :spin="repeatingOrderId === order.id" />
                            {{ ctrans("Order again") }}
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="insights.recommendations.length" id="recommendations" class="border-t border-stone-200 pt-6">
            <h3 class="text-xl font-bold text-stone-900">{{ recommendationsTitle }}</h3>
            <p class="text-sm text-stone-500">{{ recommendationsSubtitle }}</p>
            <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-6">
                <div v-for="product in insights.recommendations.slice(0, 6)" :key="product.id" class="group flex flex-col">
                    <a :href="readOnly ? undefined : product.url" class="flex h-28 items-center justify-center overflow-hidden rounded-md bg-stone-50">
                        <Image
                            v-if="product.web_images?.main"
                            :src="product.web_images.main.gallery ?? product.web_images.main.thumbnail ?? product.web_images.main.original"
                            :alt="product.name"
                            class="flex h-full w-full justify-center transition group-hover:scale-105"
                        />
                    </a>
                    <a :href="readOnly ? undefined : product.url" class="mt-2 line-clamp-2 text-sm font-medium text-stone-900 hover:underline">{{ product.name }}</a>
                    <div class="mt-auto flex items-center justify-between pt-1">
                        <p class="text-sm tabular-nums text-stone-700">{{ money(product.discounted_price ?? product.price) }}</p>
                        <button
                            type="button"
                            class="rounded-md p-1.5 text-[#8a5a3e] hover:bg-[#f8efe4] disabled:opacity-50"
                            :disabled="readOnly || addingProductIds.includes(product.id)"
                            :aria-label="ctrans('Add to basket')"
                            v-tooltip="ctrans('Add to basket')"
                            @click="addToBasket(product.id, 0, 1)"
                        >
                            <FontAwesomeIcon :icon="faShoppingBasket" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-stone-200 pt-6">
            <h3 class="text-xl font-bold text-stone-900">{{ ctrans("Your order overview") }}</h3>
            <p v-if="!hasHistory" class="text-sm text-stone-500">{{ ctrans("Your summary will appear after your first order.") }}</p>
            <dl class="mt-4 grid grid-cols-1 divide-y divide-stone-200 text-center sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div v-for="stat in overview" :key="stat.label" class="px-4 py-3">
                    <dt class="text-sm text-stone-500">{{ stat.label }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ stat.value }}</dd>
                    <dd v-if="stat.hint" class="mt-1 text-xs text-stone-500">{{ stat.hint }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>
