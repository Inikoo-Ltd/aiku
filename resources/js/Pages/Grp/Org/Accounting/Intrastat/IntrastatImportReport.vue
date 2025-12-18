<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Dec 2024 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { faFileImport } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { capitalize } from "@/Composables/capitalize"

library.add(faFileImport)

const props = defineProps<{
    data: object
    title: string
    pageHead: object
    filters: {
        countries: Array<{
            id: number
            name: string
            code: string
        }>
    }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <!-- Table -->
    <Table :resource="data" class="mt-5">
        <template #cell(date)="{ item }">
            <span class="text-sm text-gray-900">
                {{ item.date }}
            </span>
        </template>

        <template #cell(tariff_code)="{ item }">
            <span class="font-mono text-sm text-gray-900">
                {{ item.tariff_code }}
            </span>
        </template>

        <template #cell(country)="{ item }">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-900">
                    {{ item.country.code }}
                </span>
                <span class="text-xs text-gray-500">
                    {{ item.country.name }}
                </span>
            </div>
        </template>

        <template #cell(tax_category)="{ item }">
            <span class="text-sm text-gray-700">
                {{ item.tax_category.name }}
            </span>
        </template>

        <template #cell(deliveries)="{ item }">
            <div class="flex flex-col items-end">
                <span class="text-sm text-gray-900">
                    {{ item.supplier_deliveries_count }}
                </span>
                <div v-if="item.supplier_tax_numbers && item.supplier_tax_numbers.length > 0" class="flex gap-1 text-xs">
                    <span class="text-green-600">✓ {{ item.valid_tax_numbers_count }}</span>
                    <span v-if="item.invalid_tax_numbers_count > 0" class="text-red-600">
                        ✗ {{ item.invalid_tax_numbers_count }}
                    </span>
                </div>
            </div>
        </template>

        <template #cell(quantity)="{ item }">
            <span class="text-sm text-right text-gray-900">
                {{ item.quantity }}
            </span>
        </template>

        <template #cell(value_org_currency)="{ item }">
            <span class="text-sm text-right font-medium text-gray-900">
                {{ item.value_org_currency }} {{ item.currency_code }}
            </span>
        </template>

        <template #cell(weight)="{ item }">
            <span class="text-sm text-right text-gray-700">
                {{ item.weight }} kg
            </span>
        </template>
    </Table>
</template>
