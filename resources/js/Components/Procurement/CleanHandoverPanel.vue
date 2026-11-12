<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 01 Sept 2026 12:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faClipboardList, faInfoCircle } from '@fal'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Dialog from 'primevue/dialog'

library.add(faClipboardList, faInfoCircle)

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
    hygiene?: {
        exclusion_rate: number | null
        handed_over_missing_checks: number
    }
    handover_tolerance_days: number
}

defineProps<{
    data: CleanHandoverData
    showHygiene?: boolean
}>()

const locale = inject('locale', aikuLocaleStructure)

const isCalculationShown = ref(false)
</script>

<template>
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="flex items-center gap-3 border-b border-gray-900/5 bg-gray-50/80 px-6 py-5">
            <FontAwesomeIcon icon="fal fa-clipboard-list" class="text-gray-400" fixed-width aria-hidden="true" />
            <h2 class="text-base font-semibold text-gray-900">{{ ctrans('Clean Handover Score') }}</h2>
            <Button
                class="ml-auto"
                type="tertiary"
                size="xs"
                icon="fal fa-info-circle"
                :label="ctrans('How is it calculated?')"
                @click="isCalculationShown = true"
            />
        </div>

        <Dialog
            v-model:visible="isCalculationShown"
            modal
            dismissableMask
            :header="ctrans('How the Clean Handover Score is calculated')"
            :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }"
        >
            <div class="space-y-3 text-sm text-gray-700">
                <p>{{ ctrans('Each purchase order through the agent counts in the calendar quarter of its proposed ready date. The last four quarters are shown; cancelled orders are left out.') }}</p>
                <div>
                    <p class="font-medium text-gray-900">{{ ctrans('An order is clean when all of these are true:') }}</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                        <li>{{ ctrans('Handed over no later than :days days after the proposed ready date (for an agent receiving goods in its own warehouse, the goods receipt that completed the order)', { days: data.handover_tolerance_days }) }}</li>
                        <li>{{ ctrans('QC passed') }}</li>
                        <li>{{ ctrans('Compliance complete') }}</li>
                        <li>{{ ctrans('Not marked as not received') }}</li>
                    </ul>
                </div>
                <p>
                    <span class="font-medium text-gray-900">{{ ctrans('CHS') }}</span>
                    = {{ ctrans('value of clean orders ÷ scheduled value of the quarter × 100') }}.
                    {{ ctrans('Each order is weighted by the value of its goods, so a large late order lowers the score more than a small one. Orders excluded by management count in neither value.') }}
                </p>
                <div>
                    <p class="font-medium text-gray-900">{{ ctrans('Commission') }}</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                        <li><span class="font-semibold text-green-600">80% {{ ctrans('or more') }}</span>: 3%</li>
                        <li><span class="font-semibold text-amber-600">70% – 79.99%</span>: 2.5%</li>
                        <li><span class="font-semibold text-red-600">{{ ctrans('below 70%') }}</span>: 2%</li>
                    </ul>
                </div>
                <div v-if="showHygiene">
                    <p class="font-medium text-gray-900">{{ ctrans('Hygiene') }}</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                        <li>{{ ctrans('Excluded from score: share of the order value left out of the score. Above 10% shows in red.') }}</li>
                        <li>{{ ctrans('Handed over w/o QC or compliance: orders handed over before QC passed or compliance was complete.') }}</li>
                    </ul>
                </div>
            </div>
        </Dialog>

        <div v-if="data.quarters.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-900/5 text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                        <th class="px-6 py-3">{{ ctrans('Quarter') }}</th>
                        <th class="px-6 py-3 text-right">{{ ctrans('POs') }}</th>
                        <th class="px-6 py-3 text-right">{{ ctrans('Clean') }}</th>
                        <th class="px-6 py-3 text-right">{{ ctrans('Scheduled value') }}</th>
                        <th class="px-6 py-3 text-right">{{ ctrans('CHS') }}</th>
                        <th class="px-6 py-3 text-right">{{ ctrans('Commission') }}</th>
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
            {{ ctrans('No purchase orders with a proposed ready date yet') }}
        </p>

        <div v-if="showHygiene" class="grid grid-cols-1 gap-4 border-t border-gray-900/5 bg-gray-50/60 px-6 py-4 text-sm sm:grid-cols-2">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans('Excluded from score') }}</div>
                <div class="font-semibold text-gray-900"
                    :class="(data.hygiene.exclusion_rate ?? 0) > 10 ? 'text-red-600' : ''">
                    {{ data.hygiene.exclusion_rate === null ? '—' : data.hygiene.exclusion_rate + '%' }}
                </div>
            </div>
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans('Handed over w/o QC or compliance') }}</div>
                <div class="font-semibold" :class="data.hygiene.handed_over_missing_checks > 0 ? 'text-red-600' : 'text-gray-900'">
                    {{ data.hygiene.handed_over_missing_checks }}
                </div>
            </div>
        </div>
    </div>
</template>
