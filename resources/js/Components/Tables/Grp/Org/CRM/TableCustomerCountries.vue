<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { RouteParams } from "@/types/route-params"

const props = defineProps<{
    data: {}
    tab?: string
}>()

function countryUrl(countryCode: string): string {
    return route('grp.org.shops.show.crm.countries.show', {
        organisation: (route().params as RouteParams).organisation,
        shop: (route().params as RouteParams).shop,
        country: countryCode,
    })
}

function customersUrl(countryCode: string, extra: Record<string, string> = {}): string {
    const base = route('grp.org.shops.show.crm.customers.index', {
        organisation: (route().params as RouteParams).organisation,
        shop: (route().params as RouteParams).shop,
    })
    const params = new URLSearchParams({ 'filter[country]': countryCode, ...extra })

    new URLSearchParams(window.location.search).forEach((value, key) => {
        if (key.startsWith('between[')) {
            params.set(key, value)
        }
    })

    return `${base}?${params.toString()}`
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(country_code)="{ item }">
            <div class="flex items-center gap-x-2">
                <img
                    v-if="item.country_code"
                    class="inline h-[1em]"
                    :src="`/flags/${item.country_code.toLowerCase()}.png`"
                    :alt="item.country_code"
                    :title="capitalize(item.country_name)"
                />
                <Link :href="countryUrl(item.country_code)" class="primaryLink">
                    {{ capitalize(item.country_name) }}
                </Link>
            </div>
        </template>

        <template #cell(total)="{ item }">
            <Link :href="customersUrl(item.country_code)" class="primaryLink">
                {{ item.total }}
            </Link>
        </template>

        <template #cell(number_ordered)="{ item }">
            <Link :href="customersUrl(item.country_code, { 'filter[has_orders]': '1' })" class="primaryLink">
                {{ item.number_ordered }}
            </Link>
        </template>

        <template #cell(number_never_ordered)="{ item }">
            <Link :href="customersUrl(item.country_code, { 'filter[has_orders]': '0' })" class="primaryLink">
                {{ item.number_never_ordered }}
            </Link>
        </template>

        <template #cell(number_active)="{ item }">
            <Link :href="customersUrl(item.country_code, { 'filter[tag]': 'active' })" class="primaryLink">
                {{ item.number_active }}
            </Link>
        </template>

        <template #cell(number_losing)="{ item }">
            <Link :href="customersUrl(item.country_code, { 'filter[tag]': 'at-risk' })" class="primaryLink">
                {{ item.number_losing }}
            </Link>
        </template>

        <template #cell(number_lost)="{ item }">
            <Link :href="customersUrl(item.country_code, { 'filter[tag]': 'lost-customer' })" class="primaryLink">
                {{ item.number_lost }}
            </Link>
        </template>
    </Table>
</template>
