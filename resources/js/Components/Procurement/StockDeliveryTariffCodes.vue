<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 27 Oct 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import SegmentedToggle from '@/Components/Utils/SegmentedToggle.vue'
import { useLocaleStore } from '@/Stores/locale'
import { ctrans } from '@/Composables/useTrans'
import { faDownload } from '@fal'

type TariffLocale = 'en' | 'sk' | 'es'

const props = defineProps<{
    data: {
        org_currency: string
        description_locale: TariffLocale
        rows: {
            tariff_code: string | null
            origin: string | null
            descriptions: Record<TariffLocale, string | null>
            codes: string[]
            units: number
            weight: number
            value: number
        }[]
    }
    tab?: string
}>()

const locale = useLocaleStore()

const descriptionLocale = ref<TariffLocale>(props.data.description_locale)
const descriptionLocaleOptions: { label: string; value: TariffLocale }[] = [
    { label: 'EN', value: 'en' },
    { label: 'SK', value: 'sk' },
    { label: 'ES', value: 'es' },
]

function download() {
    const cell = (value: string | number | null) => `"${String(value ?? '').replaceAll('"', '""')}"`
    const rows = [
        [ctrans('Tariff code'), ctrans('Description'), ctrans('Origin'), ctrans('References'), ctrans('Units'), ctrans('Weight (kg)'), `${ctrans('Value')} (${props.data.org_currency})`],
        ...props.data.rows.map(row => [row.tariff_code, row.descriptions[descriptionLocale.value], row.origin, row.codes.join(', '), row.units, row.weight, row.value]),
    ]
    const csv = '﻿' + rows.map(row => row.map(cell).join(',')).join('\r\n')
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
    link.download = `tariff-codes-${descriptionLocale.value}.csv`
    link.click()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
}
</script>

<template>
    <div class="p-4">
        <div class="mb-3 flex flex-wrap items-center justify-end gap-2">
            <SegmentedToggle v-model="descriptionLocale" :options="descriptionLocaleOptions" :ariaLabel="ctrans('Description language')" />
            <Button :icon="faDownload" :label="ctrans('Export')" type="tertiary" size="xs" :disabled="!data.rows.length" @click="download" />
        </div>

        <div v-if="!data.rows.length" class="py-8 text-center text-sm text-gray-500">{{ ctrans('No items') }}</div>
        <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="whitespace-nowrap text-left text-xs text-gray-500">
                    <tr>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('Tariff code') }}</th>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('Description') }}</th>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('Origin') }}</th>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('References') }}</th>
                        <th class="py-2 pr-3 text-right font-normal">{{ ctrans('Units') }}</th>
                        <th class="py-2 pr-3 text-right font-normal">{{ ctrans('Weight') }}</th>
                        <th class="py-2 text-right font-normal">{{ ctrans('Value') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in data.rows" :key="`${row.tariff_code}|${row.origin}`">
                        <td class="whitespace-nowrap py-1.5 pr-3 tabular-nums">
                            <span v-if="row.tariff_code">{{ row.tariff_code }}</span>
                            <span v-else class="font-semibold text-red-600">{{ ctrans('Missing') }}</span>
                        </td>
                        <td class="py-1.5 pr-3 text-gray-600">{{ row.descriptions[descriptionLocale] ?? '-' }}</td>
                        <td class="py-1.5 pr-3">
                            <span v-if="row.origin">{{ row.origin }}</span>
                            <span v-else class="font-semibold text-red-600">{{ ctrans('Missing') }}</span>
                        </td>
                        <td class="py-1.5 pr-3">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="code in row.codes" :key="code" class="whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ code }}</span>
                            </div>
                        </td>
                        <td class="py-1.5 pr-3 text-right tabular-nums">{{ locale.number(row.units) }}</td>
                        <td class="whitespace-nowrap py-1.5 pr-3 text-right tabular-nums">{{ locale.number(row.weight) }} kg</td>
                        <td class="whitespace-nowrap py-1.5 text-right tabular-nums">{{ locale.currencyFormat(data.org_currency, row.value) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
