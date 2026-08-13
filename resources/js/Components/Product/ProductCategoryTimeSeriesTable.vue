<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 30 Dec 2025 17:00:00 Western Indonesia Time, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang='ts'>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTriangle, faEquals, faMinus } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import Table from '@/Components/Table/Table.vue'
import { useLocaleStore } from '@/Stores/locale'

library.add(faTriangle, faEquals, faMinus)

const props = defineProps<{
    data: object
    tab?: string
}>()

const locale = useLocaleStore()
const page = usePage()

const currency = computed(() => {
    return page.props?.auth?.organisation?.currency?.code ||
           page.props?.shop?.currency?.code ||
           'GBP'
})

const getInvoicesRoute = (filterDate?: string) => {
    const currentRouteName = route().current() as string
    if (!currentRouteName) {
        return null
    }

    const invoicesRouteName = currentRouteName.replace(/\.show$/, '.invoices')
    if (invoicesRouteName === currentRouteName) {
        return null
    }

    if (route().has(invoicesRouteName)) {
        const { tab, ...routeParams } = route().params as Record<string, string>

        const params = filterDate
            ? { ...routeParams, 'between[date]': filterDate }
            : routeParams

        return route(invoicesRouteName, params)
    }

    return null
}

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return { icon: faTriangle }
    } else {
        return { icon: faTriangle, class: "rotate-180" }
    }
}

const getIntervalStateColor = (isPositive: boolean) => {
    return isPositive ? "text-green-500" : "text-red-500"
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(period)="{ item }">
            <span class="font-medium text-gray-700">{{ item.period }}</span>
        </template>

        <template #cell(sales_external)="{ item }">
            <div v-if="item.sales_external" :class="item.sales_external >= 0 ? 'text-gray-700' : 'text-red-500'">
                {{ locale.currencyFormat(item.currency_code ?? currency, item.sales_external) }}
            </div>
        </template>

        <template #cell(sales_org_currency_external)="{ item }">
            <div v-if="item.sales_org_currency_external" :class="item.sales_org_currency_external >= 0 ? 'text-gray-700' : 'text-red-500'">
                {{ locale.currencyFormat(item.currency_code ?? currency, item.sales_org_currency_external) }}
            </div>
        </template>

        <template #cell(sales_org_currency_external_delta)="{ item }">
            <div v-if="item.sales_org_currency_external_delta"
                v-tooltip="locale.currencyFormat(item.currency_code ?? currency, item.sales_org_currency_external_ly)">
                <span class="tabular-nums">{{ item.sales_org_currency_external_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_org_currency_external_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_org_currency_external_delta.is_positive).class,
                        getIntervalStateColor(item.sales_org_currency_external_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(sales_grp_currency_external)="{ item }">
            <div v-if="item.sales_grp_currency_external" :class="item.sales_grp_currency_external >= 0 ? 'text-gray-700' : 'text-red-500'">
                {{ locale.currencyFormat(item.currency_code ?? currency, item.sales_grp_currency_external) }}
            </div>
        </template>

        <template #cell(invoices)="{ item }">
            <div class="text-gray-700">
                <Link v-if="getInvoicesRoute(item.filter_date)" :href="getInvoicesRoute(item.filter_date)" class="primaryLink">
                    {{ locale.number(item.invoices) }}
                </Link>
                <span v-else>{{ locale.number(item.invoices) }}</span>
            </div>
        </template>

        <template #cell(refunds)="{ item }">
            <div :class="item.refunds > 0 ? 'text-red-500' : 'text-gray-700'">
                {{ locale.number(item.refunds) }}
            </div>
        </template>

        <template #cell(customers_invoiced)="{ item }">
            <div class="text-gray-700">
                {{ locale.number(item.customers_invoiced) }}
            </div>
        </template>
    </Table>
</template>
