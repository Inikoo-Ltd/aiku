<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Fri, 05 Dec 2025 14:26:36 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
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
    intervals: {
        options: Array<{
            label: string
            labelShort: string
            value: string
            route_interval_args: string
        }>
        value: string
        range_interval: string
    }
    filters: {
        countries: Array<{
            id: number
            name: string
            code: string
        }>
    }
}>()

// Export function
const exportXml = () => {
    router.post(
        route('grp.org.reports.intrastat.export', route().params),
        {},
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button>
            <a :href="route('grp.org.reports.intrastat.export', route().params)" download target="_blank">
                <Button
                    :style="'secondary'"
                    icon="fal fa-file-export"
                    label="Export XML"
                />
            </a>
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

        <template #cell(tax_category)="{ item }">
            <span class="text-sm text-gray-700">
                {{ item.tax_category.name }}
            </span>
        </template>

        <template #cell(quantity)="{ item }">
            <span class="text-sm text-right text-gray-900">
                {{ item.quantity }}
            </span>
        </template>

        <template #cell(value)="{ item }">
            <span class="text-sm text-right font-medium text-gray-900">
                {{ item.value }}
            </span>
        </template>

        <template #cell(weight)="{ item }">
            <span class="text-sm text-right text-gray-700">
                {{ item.weight }} kg
            </span>
        </template>
    </Table>
</template>
