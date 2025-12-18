<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Fri, 05 Dec 2025 14:26:36 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faFileExport } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { capitalize } from "@/Composables/capitalize"

library.add(faFileExport)

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
    <PageHeading :data="pageHead">
        <template #button>
            <div class="flex gap-2">
                <a :href="route('grp.org.reports.intrastat.exports.export', route().params)" download target="_blank">
                    <Button
                        :style="'secondary'"
                        icon="fal fa-file-export"
                        label="Export XML"
                    />
                </a>
                <a :href="route('grp.org.reports.intrastat.exports.export-slovakia', route().params)" download target="_blank">
                    <Button
                        :style="'tertiary'"
                        icon="fal fa-file-export"
                        label="Export Slovakia XML"
                    />
                </a>
            </div>
        </template>
    </PageHeading>

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

        <template #cell(delivery_type)="{ item }">
            <span class="text-xs px-2 py-1 rounded-full" :class="{
                'bg-blue-100 text-blue-800': item.delivery_note_type === 'order',
                'bg-orange-100 text-orange-800': item.delivery_note_type === 'replacement'
            }">
                {{ item.delivery_note_type === 'order' ? 'Order' : 'Replacement' }}
            </span>
        </template>

        <template #cell(tax_category)="{ item }">
            <span class="text-sm text-gray-700">
                {{ item.tax_category.name }}
            </span>
        </template>

        <template #cell(invoices)="{ item }">
            <div class="flex flex-col items-end">
                <span class="text-sm text-gray-900">
                    {{ item.invoices_count }}
                </span>
                <div v-if="item.partner_tax_numbers && item.partner_tax_numbers.length > 0" class="flex gap-1 text-xs">
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
