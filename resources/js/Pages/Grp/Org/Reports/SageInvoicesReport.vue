<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faFileInvoice, faFileDownload } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { capitalize } from "@/Composables/capitalize"

library.add(faFileInvoice, faFileDownload)

const props = defineProps<{
    data: object
    title: string
    pageHead: object
}>()

const exportExcel = () => {
    const params = route().params
    const queryString = new URLSearchParams(window.location.search)

    const exportParams = {
        ...params,
        type: 'xlsx'
    }

    if (queryString.has('between[date]')) {
        exportParams['between[date]'] = queryString.get('between[date]')
    }

    window.location.href = route('grp.org.reports.sage-invoices.export', exportParams)
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button>
            <Button
                @click="exportExcel"
                :style="'secondary'"
                icon="fal fa-file-download"
                label="Export Excel"
            />
        </template>
    </PageHeading>

    <!-- Table -->
    <Table :resource="data" class="mt-5">
        <template #cell(date)="{ item }">
            <span class="text-sm text-gray-900">
                {{ item.date }}
            </span>
        </template>

        <template #cell(reference)="{ item }">
            <span class="font-mono text-sm font-medium text-gray-900">
                {{ item.reference }}
            </span>
        </template>

        <template #cell(customer_name)="{ item }">
            <div class="flex flex-col">
                <span class="text-sm font-medium text-gray-900">
                    {{ item.customer.name }}
                </span>
                <span v-if="item.customer.company_name" class="text-xs text-gray-500">
                    {{ item.customer.company_name }}
                </span>
            </div>
        </template>

        <template #cell(accounting_reference)="{ item }">
            <span class="font-mono text-sm text-gray-700">
                {{ item.customer.accounting_reference }}
            </span>
        </template>

        <template #cell(type)="{ item }">
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="item.type.code === 'SI'
                        ? 'bg-blue-100 text-blue-800'
                        : 'bg-orange-100 text-orange-800'"
                >
                    {{ item.type.code }}
                </span>
                <span class="text-xs text-gray-500">
                    {{ item.type.label }}
                </span>
            </div>
        </template>

        <template #cell(net_amount)="{ item }">
            <span class="text-sm text-right font-medium text-gray-900">
                {{ item.currency.symbol }}{{ item.net_amount }}
            </span>
        </template>

        <template #cell(tax_amount)="{ item }">
            <span class="text-sm text-right text-gray-700">
                {{ item.currency.symbol }}{{ item.tax_amount }}
            </span>
        </template>

        <template #cell(total_amount)="{ item }">
            <span class="text-sm text-right font-bold text-gray-900">
                {{ item.currency.symbol }}{{ item.total_amount }}
            </span>
        </template>
    </Table>
</template>
