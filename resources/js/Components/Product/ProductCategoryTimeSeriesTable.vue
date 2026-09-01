<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 30 Dec 2025 17:00:00 Western Indonesia Time, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang='ts'>
import { computed, ref } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTriangle, faEquals, faMinus } from '@fas'
import { faChevronRight } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import Table from '@/Components/Table/Table.vue'
import { useLocaleStore } from '@/Stores/locale'

library.add(faTriangle, faEquals, faMinus, faChevronRight, faSpinnerThird)

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

const expandedRows = ref<Record<number, boolean>>({})
const organisationRows = ref<Record<number, any[]>>({})
const loadingRows = ref<Record<number, boolean>>({})

const records = computed<any[]>(() => (props.data as any)?.data ?? [])

const hasOrganisationBreakdown = computed(() => records.value.some((record: any) => record.organisations_route))

const recordAt = (rowIndex: number) => records.value[rowIndex]

const toggleOrganisations = async (record: any) => {
    if (!record?.organisations_route) {
        return
    }

    expandedRows.value[record.id] = !expandedRows.value[record.id]

    if (!expandedRows.value[record.id] || organisationRows.value[record.id]) {
        return
    }

    loadingRows.value[record.id] = true
    try {
        const { data } = await axios.get(
            route(record.organisations_route.name, record.organisations_route.parameters)
        )
        organisationRows.value[record.id] = data?.data ?? []
    } finally {
        loadingRows.value[record.id] = false
    }
}

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
    <Table :resource="data" :name="tab" :useExpandTable="hasOrganisationBreakdown" class="mt-5">
        <template #cell(period)="{ item }">
            <div class="flex items-center gap-x-1.5">
                <button
                    v-if="item.organisations_route"
                    @click="toggleOrganisations(item)"
                    v-tooltip="trans('Sales by organisation')"
                    class="text-gray-400 hover:text-gray-600"
                >
                    <FontAwesomeIcon
                        :icon="faChevronRight"
                        class="text-[10px] transition-transform duration-200"
                        :class="expandedRows[item.id] ? 'rotate-90' : ''"
                        fixed-width
                        aria-hidden="true"
                    />
                </button>
                <span class="font-medium text-gray-700">{{ item.period }}</span>
            </div>
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

        <template #cell(sales_grp_currency_external_delta)="{ item }">
            <div v-if="item.sales_grp_currency_external_delta"
                v-tooltip="trans('Same period last year') + ': ' + locale.currencyFormat(item.currency_code ?? currency, item.sales_grp_currency_external_ly)">
                <span class="tabular-nums">{{ item.sales_grp_currency_external_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive).class,
                        getIntervalStateColor(item.sales_grp_currency_external_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else class="text-gray-300">
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
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

        <template #cell(total_customers)="{ item }">
            <div class="text-gray-500" v-tooltip="trans('Customers who ever bought, since the beginning')">
                {{ locale.number(item.total_customers) }}
            </div>
        </template>

        <template #expandRow="{ item }">
            <div
                v-if="recordAt(item.rowIndex) && expandedRows[recordAt(item.rowIndex).id]"
                class="bg-gray-50 border-y border-gray-200 px-4 py-2"
            >
                <div v-if="loadingRows[recordAt(item.rowIndex).id]" class="flex items-center gap-x-2 py-2 text-xs text-gray-400">
                    <FontAwesomeIcon :icon="faSpinnerThird" class="animate-spin" fixed-width aria-hidden="true" />
                    {{ trans('Loading') }}
                </div>

                <div v-else-if="!organisationRows[recordAt(item.rowIndex).id]?.length" class="py-2 text-xs text-gray-400">
                    {{ trans('No sales in this period') }}
                </div>

                <table v-else class="w-full text-xs">
                    <thead>
                        <tr class="text-gray-500">
                            <th class="py-1 pl-6 text-left font-medium">{{ trans('Organisation') }}</th>
                            <th class="py-1 pr-2 text-right font-medium">{{ trans('Sales') }}</th>
                            <th class="py-1 pr-2 text-right font-medium">{{ trans('Δ 1Y') }}</th>
                            <th class="py-1 pr-2 text-right font-medium">{{ trans('Invoices') }}</th>
                            <th class="py-1 pr-2 text-right font-medium">{{ trans('Refunds') }}</th>
                            <th class="py-1 pr-2 text-right font-medium">{{ trans('Customers') }}</th>
                            <th class="py-1 pr-2 text-right font-medium">{{ trans('Total customers') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="organisation in organisationRows[recordAt(item.rowIndex).id]" :key="organisation.organisation_id">
                            <td class="py-1 pl-6 text-gray-700">{{ organisation.name }}</td>
                            <td class="py-1 pr-2 text-right tabular-nums text-gray-700">
                                {{ locale.currencyFormat(organisation.currency_code ?? currency, organisation.sales_grp_currency_external) }}
                            </td>
                            <td class="py-1 pr-2 text-right tabular-nums">
                                <span
                                    v-if="organisation.sales_grp_currency_external_delta"
                                    v-tooltip="trans('Same period last year') + ': ' + locale.currencyFormat(organisation.currency_code ?? currency, organisation.sales_grp_currency_external_ly)"
                                    :class="getIntervalStateColor(organisation.sales_grp_currency_external_delta.is_positive)"
                                >
                                    {{ organisation.sales_grp_currency_external_delta.formatted }}
                                </span>
                                <span v-else class="text-gray-300">-</span>
                            </td>
                            <td class="py-1 pr-2 text-right tabular-nums text-gray-700">{{ locale.number(organisation.invoices) }}</td>
                            <td class="py-1 pr-2 text-right tabular-nums" :class="organisation.refunds > 0 ? 'text-red-500' : 'text-gray-700'">
                                {{ locale.number(organisation.refunds) }}
                            </td>
                            <td class="py-1 pr-2 text-right tabular-nums text-gray-700">{{ locale.number(organisation.customers_invoiced) }}</td>
                            <td class="py-1 pr-2 text-right tabular-nums text-gray-500">{{ locale.number(organisation.total_customers) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </Table>
</template>
