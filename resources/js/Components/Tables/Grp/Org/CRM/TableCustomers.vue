<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FulfilmentCustomer } from "@/types/fulfilment-customer"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useLocaleStore } from "@/Stores/locale"
import { RouteParams } from "@/types/route-params"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStar } from "@fas"
import { ctrans, ctransChoice } from "@/Composables/useTrans"

defineProps<{
    data: object,
    tab?: string
    useTopPagination?: boolean
}>()


function customerRoute(customer: FulfilmentCustomer) {
    switch (route().current()) {
        case "shops.show.customers.index":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [customer.shop_slug, customer.slug])
        case "grp.fulfilment.customers.index":
            return route(
                "grp.fulfilment.customers.show",
                [customer.slug])
        case "grp.overview.crm.customers.index":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [customer.organisation_slug, customer.shop_slug, customer.slug])
        case "grp.marketing.channels.show":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [customer.organisation_slug, customer.shop_slug, customer.slug])
        case "grp.org.overview.customers.index":
        case "grp.org.overview.crm.customers.index":
        case "grp.org.marketing.channels.show":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [(route().params as RouteParams).organisation, customer.shop_slug, customer.slug])
        default:
            return route(
                "grp.org.shops.show.crm.customers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    customer.slug
                ])
    }
}

function shopRoute(customer: FulfilmentCustomer) {
    return route(
        "shops.show",
        [customer.shop_slug])
}

function tagColorClass(scope?: string) {
    const normalized = (scope || "").toLowerCase()

    switch (normalized) {
        case "system customer":
            return "bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100"
        case "admin customer":
            return "bg-red-50 text-red-700 border-red-200 hover:bg-red-100"
        case "user customer":
            return "bg-green-50 text-green-700 border-green-200 hover:bg-green-100"
        default:
            return "bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100"
    }
}

const shortLocation = (location: string[]) => {
    const place = location?.[2] ?? ''

    return [location?.[0], location?.[1], place.length > 16 ? place.slice(0, 16) + '…' : place]
}
</script>

<template>
    <!-- ponytail: font shrunk with arbitrary variants on a wrapper, Table has no font prop -->
    <div class="[&_td]:!text-xs [&_th_div]:!text-xs">
    <Table :resource="data" :name="tab" :useTopPagination="useTopPagination" class="mt-5">
        <template #cell(reference)="{ item: customer }">
            <Link v-if="customerRoute(customer)" :href="customerRoute(customer) as string" class="primaryLink whitespace-nowrap">
                {{ customer["reference"] }}
            </Link>
            <span v-else class="whitespace-nowrap">
                {{ customer["reference"] }}
            </span>
            <FontAwesomeIcon
                v-if="customer.is_vip"
                :icon="faStar"
                size="sm"
                color="#FFC000"
                v-tooltip="ctrans('VIP Customer')" fixed-width
            />
        </template>
        <template #cell(shop)="{ item: customer }" class="primaryLink">
            <Link :href="shopRoute(customer)">
                {{ customer["shop"] }}
            </Link>
        </template>
        <template #cell(location)="{ item: customer }">
            <span v-tooltip="customer['location']?.[2]" class="whitespace-nowrap">
                <AddressLocation :data="shortLocation(customer['location'])" />
            </span>
        </template>

        <template #cell(language_code)="{ item: customer }">
            <span
                v-if="customer.language_code"
                v-tooltip="customer.language_name"
                class="whitespace-nowrap uppercase"
                :class="customer.language_is_shop ? 'text-gray-400' : 'text-gray-600'"
            >
                {{ customer.language_code }}
            </span>
            <span v-else class="text-gray-300">–</span>
        </template>
        <template #cell(number_customer_sales_channels)="{ item: customer }">
            <div v-if="customer.channel_platforms?.length" class="flex flex-nowrap items-center gap-2">
                <span
                    v-for="platform in customer.channel_platforms"
                    :key="platform.type"
                    v-tooltip="[platform.label, ctransChoice(':count store|:count stores', platform.channels, { count: platform.channels }), platform.languages.join(', ').toUpperCase()].filter(Boolean).join(' · ')"
                    class="inline-flex items-center gap-0.5 whitespace-nowrap"
                >
                    <img v-if="platform.logo" :src="platform.logo" :alt="platform.label" class="h-4 w-4 object-contain" />
                    <span v-else class="text-xs text-gray-500">{{ platform.label }}</span>
                    <span v-if="platform.channels > 1" class="text-xs tabular-nums text-gray-500">{{ platform.channels }}</span>
                    <span
                        v-for="code in platform.languages.filter((code: string) => code !== customer.language_code)"
                        :key="code"
                        class="text-[10px] uppercase text-gray-400"
                    >{{ code }}</span>
                </span>
            </div>
            <span v-else-if="customer.channel_platforms" class="text-gray-300">–</span>
            <span v-else>{{ customer.number_customer_sales_channels }}</span>
        </template>
        <template #cell(invoiced_net_amount)="{ item: customer }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(customer.currency_code, customer.sales_all) }}</div>
        </template>
        <template #cell(sales_all)="{ item: customer }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(customer.currency_code, customer.sales_all) }}</div>
        </template>
        <template #cell(order_value_net_amount)="{ item: customer }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(customer.currency_code, customer.order_value_net_amount) }}</div>
        </template>
        <template #cell(tags)="{ item: customer }">
            <div v-if="customer.tags && customer.tags.length" class="flex flex-wrap gap-1">
                <span
                    v-for="tag in customer.tags"
                    v-tooltip="tag.scope"
                    :key="tag.id || tag.name"
                    class="inline-flex items-center px-2 py-px rounded-full text-[10px] font-medium border transition-colors duration-200 ease-in-out"
                    :class="tagColorClass(tag.scope)"
                >
                    {{ tag.name }}
                </span>
            </div>
            <div v-else class="text-gray-400 text-[10px] italic">
                {{ ctrans("No tags") }}
            </div>
        </template>
        <template #cell(upcoming_products)="{ item: customer }">
            <div v-if="customer.upcoming_products && customer.upcoming_products.length" class="flex flex-wrap gap-1">
                <span
                    v-for="product in customer.upcoming_products"
                    :key="product.code"
                    v-tooltip="product.out_of_stock ? `${product.name} (${product.status_label})` : product.name"
                    class="text-xs px-1.5 py-0.5 rounded"
                    :class="product.out_of_stock ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600'"
                >
                    {{ product.code }}
                </span>
            </div>
            <div v-else class="text-gray-400 text-xs italic">
                -
            </div>
        </template>
    </Table>
    </div>
</template>
