<script setup lang="ts">
import { inject, computed, ref, defineProps } from "vue";
import { trans } from "laravel-vue-i18n"
import ProgressBar from 'primevue/progressbar';
import Tooltip from 'primevue/tooltip';
import Dialog from 'primevue/dialog';
import CustomerLifetimeValue from "./CustomerLifetimeValue.vue";
import { faTimes, faEye } from "@fas";
import { icon, library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useFormatTime } from "@/Composables/useFormatTime";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(faTimes, faEye)

const props = defineProps<{
    data: any
    currencyCode: {}
}>()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout')

const progressBarColor = computed(() => layout?.app?.theme[0])

const isShowDetail = ref(false)

// Calculate percentages for progress bar comparison
const historicCLV = computed(() => parseFloat(props.data?.historic_clv_amount || '0'))
const predictedCLV = computed(() => parseFloat(props.data?.predicted_clv_amount || '0'))

// Perbandingan historic vs predicted
const historicPercentage = computed(() => {
    const total = historicCLV.value + predictedCLV.value
    if (total === 0) return 0
    return (historicCLV.value / total) * 100
})
</script>

<template>
    <div class="border rounded-lg p-4">
        <div class="flex flex-col gap-2">
            <div class="box-border">
                <h3 class="text-lg font-bold">{{ locale.currencyFormat(currencyCode?.code, data?.total_clv_amount) }}
                </h3>
                <span class="text-sm">{{ trans('Customer Lifetime Value (CLV)') }}</span>
            </div>

            <!-- Progress Bar Comparison using PrimeVue -->
            <div class="mb-2 cursor-pointer hover:ring-1 p-[2px] rounded-lg transition-all"
                @click="isShowDetail = true">
                <!-- Single Progress Bar: Historic (filled) vs Predicted (empty) -->
                <ProgressBar v-tooltip.top="{
                        value: `
                            <div class='flex flex-col gap-2'>
                                <div class='flex items-center gap-2'>
                                    <div class='w-3 h-3 rounded-sm' style='background-color: ${progressBarColor}'></div>
                                    <span>${trans('Historic')}: ${locale.currencyFormat(currencyCode?.code, historicCLV)}</span>
                                </div>
                                <div class='flex items-center gap-2'>
                                    <div class='w-3 h-3 bg-gray-200 rounded-sm'></div>
                                    <span>${trans('Predicted')}: ${locale.currencyFormat(currencyCode?.code, predictedCLV)}</span>
                                </div>
                            </div>
                        `,
                        escape: false
                    }" :value="historicPercentage" :showValue="false" class="comparison-progressbar"></ProgressBar>
            </div>

            <div class="space-y-2">
                <div class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Expected date of next order') }}</span>
                    <span>{{ useFormatTime(data?.expected_date_of_next_order, { formatTime: 'MMM dd, yyyy' }) }}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Avg time between orders') }}</span>
                    <span>{{ data?.average_time_between_orders }} days</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Avarage order value') }}</span>
                    <span>{{ data?.average_order_value }}</span>
                </div>
            </div>
        </div>
    </div>
    <Dialog v-model:visible="isShowDetail" modal :showHeader="false" :style="{ width: '50rem' }" closable>
        <CustomerLifetimeValue :data="data" :currencyCode="currencyCode">
            <template #close>
                <button @click="isShowDetail = false">
                    <FontAwesomeIcon :icon="faTimes" />
                </button>
            </template>
        </CustomerLifetimeValue>
    </Dialog>
</template>

<style scoped lang="scss">
// Comparison Progress Bar: Historic (filled) vs Predicted (empty/background)
::v-deep(.comparison-progressbar) {
    .p-progressbar-value {
        background-color: v-bind(progressBarColor) !important;
    }
}
</style>
