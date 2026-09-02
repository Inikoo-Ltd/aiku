<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 01 Sept 2026 12:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faClipboardList } from '@fal'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

library.add(faClipboardList)

export interface CleanHandoverData {
    quarters: {
        quarter: string
        scheduled_value: number
        clean_value: number
        excluded_value: number
        number_pos: number
        number_clean: number
        chs: number | null
        commission_rate: number | null
    }[]
    hygiene: {
        avg_ready_date_padding_days: number | null
        exclusion_rate: number | null
        handed_over_missing_checks: number
    }
}

defineProps<{
    data: CleanHandoverData
    showHygiene?: boolean
}>()

const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="flex items-center gap-3 border-b border-gray-900/5 bg-gray-50/80 px-6 py-5">
            <FontAwesomeIcon icon="fal fa-clipboard-list" class="text-gray-400" fixed-width aria-hidden="true" />
            <h2 class="text-base font-semibold text-gray-900">{{ trans('Clean Handover Score') }}</h2>
        </div>

        <div v-if="data.quarters.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-900/5 text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                        <th class="px-6 py-3">{{ trans('Quarter') }}</th>
                        <th class="px-6 py-3 text-right">{{ trans('POs') }}</th>
                        <th class="px-6 py-3 text-right">{{ trans('Clean') }}</th>
                        <th class="px-6 py-3 text-right">{{ trans('Scheduled value') }}</th>
                        <th class="px-6 py-3 text-right">{{ trans('CHS') }}</th>
                        <th class="px-6 py-3 text-right">{{ trans('Commission') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-900/5">
                    <tr v-for="q in data.quarters" :key="q.quarter">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ q.quarter }}</td>
                        <td class="px-6 py-3 text-right text-gray-700">{{ q.number_pos }}</td>
                        <td class="px-6 py-3 text-right text-gray-700">{{ q.number_clean }}</td>
                        <td class="px-6 py-3 text-right text-gray-700">{{ locale.number(q.scheduled_value) }}</td>
                        <td class="px-6 py-3 text-right font-semibold"
                            :class="q.chs === null ? 'text-gray-400' : q.chs >= 80 ? 'text-green-600' : q.chs >= 70 ? 'text-amber-600' : 'text-red-600'">
                            {{ q.chs === null ? '—' : q.chs + '%' }}
                        </td>
                        <td class="px-6 py-3 text-right text-gray-700">
                            {{ q.commission_rate === null ? '—' : q.commission_rate + '%' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="px-6 py-8 text-center text-sm text-gray-400">
            {{ trans('No purchase orders with an approved ready date yet') }}
        </p>

        <div v-if="showHygiene" class="grid grid-cols-1 gap-4 border-t border-gray-900/5 bg-gray-50/60 px-6 py-4 text-sm sm:grid-cols-3">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans('Avg ready-date shift on approval') }}</div>
                <div class="font-semibold text-gray-900">
                    {{ data.hygiene.avg_ready_date_padding_days === null ? '—' : data.hygiene.avg_ready_date_padding_days + ' ' + trans('days') }}
                </div>
            </div>
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans('Excluded from score') }}</div>
                <div class="font-semibold text-gray-900"
                    :class="(data.hygiene.exclusion_rate ?? 0) > 10 ? 'text-red-600' : ''">
                    {{ data.hygiene.exclusion_rate === null ? '—' : data.hygiene.exclusion_rate + '%' }}
                </div>
            </div>
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans('Handed over w/o QC or compliance') }}</div>
                <div class="font-semibold" :class="data.hygiene.handed_over_missing_checks > 0 ? 'text-red-600' : 'text-gray-900'">
                    {{ data.hygiene.handed_over_missing_checks }}
                </div>
            </div>
        </div>
    </div>
</template>
