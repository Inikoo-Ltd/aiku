<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router, usePage } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Dialog from "primevue/dialog"
import Table from "@/Components/Table/Table.vue"
import SeoExportButton from "@/Components/Seo/SeoExportButton.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

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

type BasketItems = {
    customer_id: number
    customer: { name: string | null, route: routeType }
    from: string | null
    to: string | null
    items: BasketItem[]
}

defineProps<{
    data: object
    tab: string
    currencyCode?: string | null
}>()

const locale = useLocaleStore()
const page = usePage()

const selectedCustomerId = ref<number | null>(null)
const isBasketOpen = ref(false)

const basket = computed(() => {
    const items = page.props.basket_items as BasketItems | null | undefined

    return items && items.customer_id === selectedCustomerId.value ? items : null
})

const basketPeriod = computed(() => {
    if (!basket.value?.from) {
        return ctrans("Whole tracked period")
    }

    return ctrans(":from to :to", {
        from: useFormatTime(basket.value.from, { formatTime: "mdy" }),
        to: useFormatTime(basket.value.to ?? new Date().toISOString(), { formatTime: "mdy" }),
    })
})

const openBasket = (customerId: number) => {
    selectedCustomerId.value = customerId
    isBasketOpen.value = true

    const params = new URLSearchParams(window.location.search)
    params.set("basket_customer", String(customerId))

    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
        only: ["basket_items"],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}
</script>

<template>
    <div class="flex justify-end px-4 pb-3 pt-1">
        <SeoExportButton table="conversions" />
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
            <button
                v-if="customer.add_to_baskets > 0"
                type="button"
                class="primaryLink tabular-nums"
                v-tooltip="ctrans('See what this customer added to the basket')"
                @click="openBasket(customer.id)">
                {{ locale.number(customer.add_to_baskets) }}
            </button>
            <span v-else class="tabular-nums">{{ locale.number(customer.add_to_baskets) }}</span>
        </template>

        <template #cell(checkouts)="{ item: customer }: { item: ConversionCustomerRow }">
            <span class="tabular-nums">{{ locale.number(customer.checkouts) }}</span>
        </template>

        <template #cell(purchases)="{ item: customer }: { item: ConversionCustomerRow }">
            <span class="tabular-nums">{{ locale.number(customer.purchases) }}</span>
        </template>

        <template #cell(revenue)="{ item: customer }: { item: ConversionCustomerRow }">
            <span class="tabular-nums">{{ currencyCode ? locale.currencyFormat(currencyCode, customer.revenue) : locale.number(customer.revenue) }}</span>
        </template>

        <template #cell(last_event_at)="{ item: customer }: { item: ConversionCustomerRow }">
            <span v-if="customer.last_event_at" class="tabular-nums">{{ useFormatTime(customer.last_event_at, { formatTime: "mdy" }) }}</span>
        </template>
    </Table>

    <Dialog v-model:visible="isBasketOpen" modal dismissableMask :header="ctrans('Added to the basket')" class="w-[min(760px,96vw)]">
        <div v-if="basket">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                <Link :href="route(basket.customer.route.name, basket.customer.route.parameters)" class="primaryLink font-medium">
                    {{ basket.customer.name }}
                </Link>
                <span class="text-xs text-gray-500">{{ basketPeriod }}</span>
            </div>

            <p v-if="!basket.items.length" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                {{ ctrans("No product was added to the basket by this customer in this period.") }}
            </p>

            <table v-else class="w-full text-sm">
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
                    <tr v-for="item in basket.items" :key="`${item.code}-${item.last_added_at}`">
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
        </div>
        <div v-else class="space-y-3" aria-busy="true">
            <div class="h-4 w-48 animate-pulse rounded bg-gray-200" />
            <div class="h-40 w-full animate-pulse rounded bg-gray-100" />
        </div>
    </Dialog>
</template>
