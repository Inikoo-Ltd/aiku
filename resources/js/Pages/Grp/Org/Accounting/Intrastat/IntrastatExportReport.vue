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
import Modal from "@/Components/Utils/Modal.vue"
import axios from "axios"
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileExport, faFileExcel, faExclamationTriangle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { capitalize } from "@/Composables/capitalize"

library.add(faFileExport, faFileExcel, faExclamationTriangle)

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

const exportExcel = () => {
    const params = route().params
    const queryString = new URLSearchParams(window.location.search)

    const exportParams: Record<string, string> = { ...params, type: 'xlsx' }

    if (queryString.has('between[from]')) {
        exportParams['between[from]'] = queryString.get('between[from]') as string
    }

    if (queryString.has('elements[vat_status]')) {
        exportParams['elements[vat_status]'] = queryString.get('elements[vat_status]') as string
    }

    window.location.href = route('grp.org.reports.intrastat.exports.export-excel', exportParams)
}

const aeatErrors = ref<string[]>([])
const aeatRows = ref(0)
const isAeatModalOpen = ref(false)
const isAeatChecking = ref(false)

const aeatUrl = (extra: Record<string, string>) => route('grp.org.reports.intrastat.exports.export-aeat', { ...route().params, ...extra })

const exportAeat = async () => {
    isAeatChecking.value = true
    try {
        const { data } = await axios.get(aeatUrl({ check: '1' }))
        aeatErrors.value = data.errors
        aeatRows.value = data.rows
        if (data.errors.length) {
            isAeatModalOpen.value = true
        } else {
            window.location.href = aeatUrl({})
        }
    } finally {
        isAeatChecking.value = false
    }
}

const exportAeatAnyway = () => {
    isAeatModalOpen.value = false
    window.location.href = aeatUrl({ force: '1' })
}
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
                        :style="'secondary'"
                        icon="fal fa-file-export"
                        label="Export Slovakia XML"
                    />
                </a>
                <Button
                    @click="exportAeat"
                    :loading="isAeatChecking"
                    :style="'secondary'"
                    icon="fal fa-file-export"
                    label="Export Spain AEAT"
                />
                <Button
                    @click="exportExcel"
                    :style="'secondary'"
                    icon="fal fa-file-excel"
                    label="Export Excel"
                />
            </div>
        </template>
    </PageHeading>

    <Modal :isOpen="isAeatModalOpen" @onClose="isAeatModalOpen = false" width="w-full max-w-4xl" :isClosableInBackground="false">
        <div class="flex items-start gap-4">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-600 text-4xl shrink-0" fixed-width aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-semibold text-red-700">{{ trans('AEAT file is not valid') }}</h2>
                <p class="mt-1 text-gray-700">
                    {{ trans(':errors problems found in :rows rows. AEAT will reject this file as it is.', { errors: aeatErrors.length, rows: aeatRows }) }}
                </p>
                <pre class="mt-4 max-h-96 overflow-auto rounded bg-gray-50 p-3 text-xs text-gray-800">{{ aeatErrors.join('\n') }}</pre>
                <div class="mt-6 flex justify-end gap-3">
                    <Button @click="isAeatModalOpen = false" :style="'secondary'" :label="trans('Fix the data first')" />
                    <Button @click="exportAeatAnyway" :style="'red'" icon="fal fa-exclamation-triangle" :label="trans('Download anyway with the errors')" />
                </div>
            </div>
        </div>
    </Modal>

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

        <template #cell(partner_tax_number)="{ item }">
            <span class="font-mono text-sm" :class="item.partner_tax_number ? 'text-gray-900' : 'text-gray-400'">
                {{ item.partner_tax_number ?? 'QV999999999999' }}
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
