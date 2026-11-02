<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router, usePage } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { watchDebounced } from "@vueuse/core"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBarcode } from "@fal"
import Table from "@/Components/Table/Table.vue"
import SeoExportButton from "@/Components/Seo/SeoExportButton.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type ConversionType = "add_to_basket" | "checkout" | "purchase"

type ConversionCustomerRow = {
    id: number
    name: string | null
    contact_name: string | null
    email: string | null
    route: routeType
    channels: string[]
    add_to_baskets: number
    checkouts: number
    purchases: number
    revenue: number
    last_event_at: string | null
}

type BasketItem = {
    code: string | null
    name: string | null
    route: routeType | null
    adds: number
    quantity: number
    last_added_at: string
}

type OrderItem = {
    code: string | null
    name: string | null
    route: routeType | null
    quantity: number
}

type OrderEvent = {
    event_id: number
    event_at: string
    reference: string | null
    route: routeType
    net_amount: number
    currency_code: string | null
    items: OrderItem[]
}

type ConversionDetail = {
    customer_id: number
    type: ConversionType
    customer: { name: string | null, route: routeType }
    from: string | null
    to: string | null
    items: BasketItem[]
    orders: OrderEvent[]
}

library.add(faBarcode)

const props = defineProps<{
    data: object
    tab: string
    currencyCode?: string | null
}>()

const locale = useLocaleStore()
const page = usePage()

const productParam = `${props.tab}_product`
const productCode = ref(new URLSearchParams(window.location.search).get(productParam) ?? "")
const appliedProductCode = ref(productCode.value)

watchDebounced(productCode, (value) => {
    const code = value.trim()

    if (code === appliedProductCode.value) {
        return
    }

    const params = new URLSearchParams(window.location.search)
    params.delete(`${props.tab}Page`)

    if (code) {
        params.set(productParam, code)
    } else {
        params.delete(productParam)
    }

    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
        only: [props.tab, "queryBuilderProps"],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => appliedProductCode.value = code,
    })
}, { debounce: 400 })

const selectedCustomerId = ref<number | null>(null)
const selectedType = ref<ConversionType>("add_to_basket")
const isDetailOpen = ref(false)

const headers: Record<ConversionType, string> = {
    add_to_basket: ctrans("Added to the basket"),
    checkout: ctrans("Checkouts"),
    purchase: ctrans("Purchases"),
}

const tooltips: Record<ConversionType, string> = {
    add_to_basket: ctrans("See what this customer added to the basket"),
    checkout: ctrans("See each checkout this customer opened and the products in the order"),
    purchase: ctrans("See each order this customer submitted and its products"),
}

const detail = computed(() => {
    const conversion = page.props.conversion_detail as ConversionDetail | null | undefined

    return conversion && conversion.customer_id === selectedCustomerId.value && conversion.type === selectedType.value ? conversion : null
})

const detailPeriod = computed(() => {
    if (!detail.value?.from) {
        return ctrans("Whole tracked period")
    }

    return ctrans(":from to :to", {
        from: useFormatTime(detail.value.from, { formatTime: "mdy" }),
        to: useFormatTime(detail.value.to ?? new Date().toISOString(), { formatTime: "mdy" }),
    })
})

const emptyText = computed(() => ({
    add_to_basket: ctrans("No product was added to the basket by this customer in this period."),
    checkout: ctrans("No checkout was opened by this customer in this period."),
    purchase: ctrans("No order was submitted by this customer in this period."),
})[selectedType.value])

const openDetail = (customerId: number, type: ConversionType) => {
    selectedCustomerId.value = customerId
    selectedType.value = type
    isDetailOpen.value = true

    const params = new URLSearchParams(window.location.search)
    params.set("conversion_customer", String(customerId))
    params.set("conversion_type", type)

    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
        only: ["conversion_detail"],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const money = (order: OrderEvent) => order.currency_code
    ? String(locale.currencyFormat(order.currency_code, order.net_amount))
    : locale.number(order.net_amount)
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 pb-3 pt-1">
        <IconField class="w-64">
            <InputIcon><FontAwesomeIcon icon="fal fa-barcode" fixed-width aria-hidden="true" /></InputIcon>
            <InputText v-model="productCode" class="h-9 w-full text-sm" :placeholder="ctrans('Product code')" :aria-label="ctrans('Product code')" />
        </IconField>
        <span v-if="appliedProductCode" class="text-xs text-gray-500">
            {{ ctrans("Only activity on products whose code starts with :code; the counts and the With / Without filters follow it.", { code: appliedProductCode }) }}
        </span>
        <SeoExportButton class="ml-auto" table="conversions" />
    </div>

    <Table :resource="data" :name="tab">
        <template #cell(name)="{ item: customer }: { item: ConversionCustomerRow }">
            <Link :href="route(customer.route.name, customer.route.parameters)" class="primaryLink">
                {{ customer.name || customer.contact_name || customer.email }}
            </Link>
        </template>

        <template #cell(traffic_source_types)="{ item: customer }: { item: ConversionCustomerRow }">
            <span v-if="customer.channels.length">{{ customer.channels.join(", ") }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(add_to_baskets)="{ item: customer }: { item: ConversionCustomerRow }">
            <button v-if="customer.add_to_baskets > 0" type="button" class="primaryLink tabular-nums" v-tooltip="tooltips.add_to_basket" @click="openDetail(customer.id, 'add_to_basket')">
                {{ locale.number(customer.add_to_baskets) }}
            </button>
            <span v-else class="tabular-nums">{{ locale.number(customer.add_to_baskets) }}</span>
        </template>

        <template #cell(checkouts)="{ item: customer }: { item: ConversionCustomerRow }">
            <button v-if="customer.checkouts > 0" type="button" class="primaryLink tabular-nums" v-tooltip="tooltips.checkout" @click="openDetail(customer.id, 'checkout')">
                {{ locale.number(customer.checkouts) }}
            </button>
            <span v-else class="tabular-nums">{{ locale.number(customer.checkouts) }}</span>
        </template>

        <template #cell(purchases)="{ item: customer }: { item: ConversionCustomerRow }">
            <button v-if="customer.purchases > 0" type="button" class="primaryLink tabular-nums" v-tooltip="tooltips.purchase" @click="openDetail(customer.id, 'purchase')">
                {{ locale.number(customer.purchases) }}
            </button>
            <span v-else class="tabular-nums">{{ locale.number(customer.purchases) }}</span>
        </template>

        <template #cell(revenue)="{ item: customer }: { item: ConversionCustomerRow }">
            <span class="tabular-nums">{{ currencyCode ? locale.currencyFormat(currencyCode, customer.revenue) : locale.number(customer.revenue) }}</span>
        </template>

        <template #cell(last_event_at)="{ item: customer }: { item: ConversionCustomerRow }">
            <span v-if="customer.last_event_at" class="tabular-nums">{{ useFormatTime(customer.last_event_at, { formatTime: "mdy" }) }}</span>
        </template>
    </Table>

    <Dialog v-model:visible="isDetailOpen" modal dismissableMask :header="headers[selectedType]" class="w-[min(760px,96vw)]">
        <div v-if="detail">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                <Link :href="route(detail.customer.route.name, detail.customer.route.parameters)" class="primaryLink font-medium">
                    {{ detail.customer.name }}
                </Link>
                <span class="text-xs text-gray-500">{{ detailPeriod }}</span>
            </div>

            <p v-if="detail.type === 'add_to_basket' ? !detail.items.length : !detail.orders.length" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                {{ emptyText }}
            </p>

            <table v-else-if="detail.type === 'add_to_basket'" class="w-full text-sm">
                <thead class="whitespace-nowrap border-b border-gray-200 text-left text-xs font-medium text-gray-500">
                    <tr>
                        <th class="py-1.5 pr-3">{{ ctrans("Code") }}</th>
                        <th class="py-1.5 pr-3">{{ ctrans("Product") }}</th>
                        <th class="py-1.5 pr-3 text-right">{{ ctrans("Times added") }}</th>
                        <th class="py-1.5 pr-3 text-right">{{ ctrans("Quantity") }}</th>
                        <th class="py-1.5 text-right">{{ ctrans("Last added") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in detail.items" :key="`${item.code}-${item.last_added_at}`">
                        <td class="whitespace-nowrap py-1.5 pr-3">
                            <Link v-if="item.route" :href="route(item.route.name, item.route.parameters)" class="primaryLink">{{ item.code }}</Link>
                            <span v-else>{{ item.code ?? "-" }}</span>
                        </td>
                        <td class="py-1.5 pr-3 text-gray-700">{{ item.name ?? "-" }}</td>
                        <td class="py-1.5 pr-3 text-right tabular-nums">{{ locale.number(item.adds) }}</td>
                        <td class="py-1.5 pr-3 text-right tabular-nums">{{ locale.number(item.quantity) }}</td>
                        <td class="whitespace-nowrap py-1.5 text-right tabular-nums text-gray-600">{{ useFormatTime(item.last_added_at, { formatTime: "mdy" }) }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-else class="space-y-4">
                <section v-for="order in detail.orders" :key="order.event_id" class="rounded-lg border border-gray-200">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-gray-100 bg-gray-50 px-3 py-2 text-sm">
                        <span class="tabular-nums text-gray-700">{{ useFormatTime(order.event_at, { formatTime: "hm" }) }}</span>
                        <Link :href="route(order.route.name, order.route.parameters)" class="primaryLink whitespace-nowrap">{{ order.reference ?? "-" }}</Link>
                        <span class="ml-auto tabular-nums text-gray-700">{{ money(order) }}</span>
                    </div>

                    <p v-if="!order.items.length" class="px-3 py-3 text-sm text-gray-500">{{ ctrans("The order has no products left in it.") }}</p>

                    <table v-else class="w-full text-sm">
                        <thead class="whitespace-nowrap border-b border-gray-100 text-left text-xs font-medium text-gray-500">
                            <tr>
                                <th class="px-3 py-1.5">{{ ctrans("Code") }}</th>
                                <th class="py-1.5 pr-3">{{ ctrans("Product") }}</th>
                                <th class="py-1.5 pr-3 text-right">{{ ctrans("Quantity") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(item, index) in order.items" :key="`${order.event_id}-${index}`">
                                <td class="whitespace-nowrap px-3 py-1.5">
                                    <Link v-if="item.route" :href="route(item.route.name, item.route.parameters)" class="primaryLink">{{ item.code }}</Link>
                                    <span v-else>{{ item.code ?? "-" }}</span>
                                </td>
                                <td class="py-1.5 pr-3 text-gray-700">{{ item.name ?? "-" }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ locale.number(item.quantity) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
        <div v-else class="space-y-3" aria-busy="true">
            <div class="h-4 w-48 animate-pulse rounded bg-gray-200" />
            <div class="h-40 w-full animate-pulse rounded bg-gray-100" />
        </div>
    </Dialog>
</template>
