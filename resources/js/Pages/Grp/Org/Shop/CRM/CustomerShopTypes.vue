<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Select from 'primevue/select'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Table from '@/Components/Table/Table.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheck } from '@fal'
import { capitalize } from '@/Composables/capitalize'
import { ctrans } from '@/Composables/useTrans'
import { useLocaleStore } from '@/Stores/locale'
import { PageHeadingTypes } from '@/types/PageHeading'
import { routeType } from '@/types/route'
import { RouteParams } from '@/types/route-params'
library.add(faCheck)

type CoverageRow = {
    key: string
    label: string
    customers: number
    source_customer: number
    source_staff: number
    source_inferred: number
}

type ShopCoverageRow = {
    code: string
    organisation_slug: string
    shop_slug: string
    active: number
    with_shop_type: number
    is_current: boolean
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    coverage: CoverageRow[]
    shopsCoverage: ShopCoverageRow[]
    canEdit: boolean
    currencyCode: string
    shopTypeOptions: { value: string; label: string }[]
    bulkUpdateRoute: routeType
    customers: {}
}>()

const locale = useLocaleStore()

const coverageRows = computed(() => props.coverage.filter((row) => row.key !== 'none' && row.customers > 0))
const noShopTypeRow = computed(() => props.coverage.find((row) => row.key === 'none'))
const activeTotal = computed(() => props.coverage.reduce((total, row) => total + row.customers, 0))
const sourceTotal = (source: 'source_customer' | 'source_staff' | 'source_inferred') => props.coverage.reduce((total, row) => total + row[source], 0)

const percentOf = (count: number, total: number) => (total ? (100 * count / total) : 0).toFixed(1) + '%'

const routeParams = route().params as RouteParams

const filterHref = (shopType: string) => route('grp.org.shops.show.crm.customers.shop_types.index', {
    organisation: routeParams.organisation,
    shop: routeParams.shop,
    elements: { shop_type: shopType },
})

const shopTypesHref = (shop: ShopCoverageRow) => route('grp.org.shops.show.crm.customers.shop_types.index', {
    organisation: shop.organisation_slug,
    shop: shop.shop_slug,
})

const customerHref = (customer: { slug: string }) => route('grp.org.shops.show.crm.customers.show', [routeParams.organisation, routeParams.shop, customer.slug])

const shortLocation = (location: string[]) => {
    const place = location?.[2] ?? ''

    return [location?.[0], location?.[1], place.length > 16 ? place.slice(0, 16) + '…' : place]
}

const shopsTotal = computed(() => ({
    active: props.shopsCoverage.reduce((total, row) => total + row.active, 0),
    with_shop_type: props.shopsCoverage.reduce((total, row) => total + row.with_shop_type, 0),
}))

const CLEAR_SHOP_TYPE = '__clear__'

const bulkOptions = computed(() => [
    ...props.shopTypeOptions,
    { value: CLEAR_SHOP_TYPE, label: ctrans('Clear shop type') },
])

const table = ref<InstanceType<typeof Table> | null>(null)
const selectedIds = ref<number[]>([])
const bulkShopType = ref<string | null>(null)
const isApplying = ref(false)
const bulkError = ref<string | null>(null)

const onSelectRow = (selection: Record<string, boolean>) => {
    selectedIds.value = Object.entries(selection).filter(([, isSelected]) => isSelected).map(([id]) => Number(id))
}

const applyToSelected = () => {
    router.patch(route(props.bulkUpdateRoute.name, props.bulkUpdateRoute.parameters), {
        customers: selectedIds.value,
        shop_type: bulkShopType.value === CLEAR_SHOP_TYPE ? null : bulkShopType.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            isApplying.value = true
            bulkError.value = null
        },
        onError: (errors) => bulkError.value = Object.values(errors)[0] ?? null,
        onSuccess: () => {
            bulkShopType.value = null
            selectedIds.value = []
            table.value?.clearSelection()
        },
        onFinish: () => isApplying.value = false,
    })
}
</script>

<template>
    <div>
        <Head :title="capitalize(title)" />
        <PageHeading :data="pageHead" />

        <div class="grid grid-cols-1 gap-8 px-4 py-4 lg:grid-cols-2">
            <section>
                <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans('Coverage of this shop') }}</h2>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="whitespace-nowrap text-left text-xs font-medium text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">{{ ctrans('Shop type') }}</th>
                            <th class="px-4 py-2 text-right">{{ ctrans('Customers') }}</th>
                            <th class="px-4 py-2 text-right">{{ ctrans('% of active') }}</th>
                            <th class="px-4 py-2 text-right">{{ ctrans('Told us') }}</th>
                            <th class="px-4 py-2 text-right">{{ ctrans('Set by staff') }}</th>
                            <th class="py-2 pl-4 text-right">{{ ctrans('Inferred') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 tabular-nums text-gray-700">
                        <tr v-for="row in coverageRows" :key="row.key">
                            <td class="py-1.5 pr-4">{{ row.label }}</td>
                            <td class="px-4 py-1.5 text-right">
                                <Link :href="filterHref(row.key)" preserve-scroll class="font-medium hover:underline">{{ locale.number(row.customers) }}</Link>
                            </td>
                            <td class="px-4 py-1.5 text-right">{{ percentOf(row.customers, activeTotal) }}</td>
                            <td class="px-4 py-1.5 text-right">{{ locale.number(row.source_customer) }}</td>
                            <td class="px-4 py-1.5 text-right">{{ locale.number(row.source_staff) }}</td>
                            <td class="py-1.5 pl-4 text-right">{{ locale.number(row.source_inferred) }}</td>
                        </tr>
                        <tr v-if="noShopTypeRow">
                            <td class="py-1.5 pr-4 text-gray-500">{{ ctrans('No shop type yet') }}</td>
                            <td class="px-4 py-1.5 text-right">
                                <Link :href="filterHref('none')" preserve-scroll class="font-medium hover:underline">{{ locale.number(noShopTypeRow.customers) }}</Link>
                            </td>
                            <td class="px-4 py-1.5 text-right">{{ percentOf(noShopTypeRow.customers, activeTotal) }}</td>
                            <td class="px-4 py-1.5 text-right text-gray-400">-</td>
                            <td class="px-4 py-1.5 text-right text-gray-400">-</td>
                            <td class="py-1.5 pl-4 text-right text-gray-400">-</td>
                        </tr>
                    </tbody>
                    <tfoot class="tabular-nums font-semibold text-gray-900">
                        <tr class="border-t border-gray-300">
                            <td class="py-1.5 pr-4">{{ ctrans('Active customers (24 months)') }}</td>
                            <td class="px-4 py-1.5 text-right">{{ locale.number(activeTotal) }}</td>
                            <td class="px-4 py-1.5 text-right">{{ percentOf(activeTotal, activeTotal) }}</td>
                            <td class="px-4 py-1.5 text-right">{{ locale.number(sourceTotal('source_customer')) }}</td>
                            <td class="px-4 py-1.5 text-right">{{ locale.number(sourceTotal('source_staff')) }}</td>
                            <td class="py-1.5 pl-4 text-right">{{ locale.number(sourceTotal('source_inferred')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <section v-if="shopsCoverage.length">
                <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans('All shops') }}</h2>
                <div class="max-h-56 overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="sticky top-0 whitespace-nowrap bg-white text-left text-xs font-medium text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">{{ ctrans('Shop') }}</th>
                            <th class="px-4 py-2 text-right">{{ ctrans('Active customers') }}</th>
                            <th class="px-4 py-2 text-right">{{ ctrans('With shop type') }}</th>
                            <th class="py-2 pl-4 text-right">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 tabular-nums text-gray-700">
                        <tr v-for="shop in shopsCoverage" :key="shop.organisation_slug + shop.shop_slug" :class="{ 'font-bold text-gray-900': shop.is_current }">
                            <td class="whitespace-nowrap py-1.5 pr-4">
                                <Link :href="shopTypesHref(shop)" class="font-medium hover:underline">{{ shop.code }}</Link>
                            </td>
                            <td class="px-4 py-1.5 text-right">{{ locale.number(shop.active) }}</td>
                            <td class="px-4 py-1.5 text-right">{{ locale.number(shop.with_shop_type) }}</td>
                            <td class="py-1.5 pl-4 text-right">{{ percentOf(shop.with_shop_type, shop.active) }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="shopsCoverage.length > 1" class="sticky bottom-0 bg-white tabular-nums font-semibold text-gray-900">
                        <tr class="border-t border-gray-300">
                            <td class="py-1.5 pr-4">{{ ctrans('All') }}</td>
                            <td class="px-4 py-1.5 text-right">{{ locale.number(shopsTotal.active) }}</td>
                            <td class="px-4 py-1.5 text-right">{{ locale.number(shopsTotal.with_shop_type) }}</td>
                            <td class="py-1.5 pl-4 text-right">{{ percentOf(shopsTotal.with_shop_type, shopsTotal.active) }}</td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </section>
        </div>

        <div v-if="canEdit" class="px-4">
            <div class="flex flex-wrap items-center gap-2">
                <Select
                    v-model="bulkShopType"
                    :options="bulkOptions"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="ctrans('Shop type')"
                    class="w-64"
                />
                <Button
                    type="tertiary"
                    :label="selectedIds.length ? ctrans('Apply to :count selected', { count: selectedIds.length }) : ctrans('Apply to selected')"
                    :disabled="!selectedIds.length || bulkShopType === null"
                    :loading="isApplying"
                    @click="applyToSelected"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                    <template #loading>
                        <LoadingIcon v-if="isApplying" />
                    </template>
                </Button>
            </div>
            <p v-if="bulkError" class="mt-1 text-xs text-red-600">{{ bulkError }}</p>
        </div>

        <Table ref="table" :resource="customers" :isCheckBox="canEdit" @onSelectRow="onSelectRow" class="mt-2">
            <template #cell(reference)="{ item: customer }">
                <Link :href="customerHref(customer)" class="whitespace-nowrap font-medium hover:underline">{{ customer.reference }}</Link>
            </template>
            <template #cell(name)="{ item: customer }">
                <div>{{ customer.name }}</div>
                <div v-if="customer.company_name && customer.company_name !== customer.name" class="text-xs text-gray-500">{{ customer.company_name }}</div>
            </template>
            <template #cell(location)="{ item: customer }">
                <span v-tooltip="customer.location?.[2]" class="whitespace-nowrap">
                    <AddressLocation :data="shortLocation(customer.location)" />
                </span>
            </template>
            <template #cell(number_invoices_type_invoice)="{ item: customer }">
                <span class="tabular-nums">{{ locale.number(customer.number_invoices_type_invoice ?? 0) }}</span>
            </template>
            <template #cell(sales_all)="{ item: customer }">
                <span class="tabular-nums text-gray-500">{{ locale.currencyFormat(currencyCode, customer.sales_all ?? 0) }}</span>
            </template>
            <template #cell(shop_type)="{ item: customer }">
                <span v-if="customer.shop_type_label">{{ customer.shop_type_label }}</span>
                <span v-else class="text-gray-400">-</span>
            </template>
            <template #cell(shop_type_source)="{ item: customer }">
                <span class="text-gray-500">{{ customer.shop_type_source_label ?? '' }}</span>
            </template>
        </Table>
    </div>
</template>
