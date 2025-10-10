<script setup lang="ts">
import { inject, computed, ref, defineProps } from "vue";
import { trans } from "laravel-vue-i18n"
import ProgressBar from 'primevue/progressbar';
import { Tooltip } from 'floating-vue'
import Dialog from 'primevue/dialog';
import CustomerLifetimeValue from "./CustomerLifetimeValue.vue";
import { faTimes, faEye } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core"
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
                <h3 class="text-lg font-bold">{{ locale.currencyFormat(currencyCode?.code, data?.total_clv_amount || 0) }}
                </h3>
                <span class="text-sm">{{ trans('Customer Lifetime Value (CLV)') }}</span>
            </div>

            <!-- Progress Bar Comparison using PrimeVue -->
            <div class="mb-2 hover:ring-1 p-[2px] cursor-pointer rounded-md" @click="isShowDetail = true">
                <!-- Single Progress Bar with Tooltip -->
                <Tooltip placement="top">
                    <ProgressBar 
                        :value="historicPercentage" 
                        :showValue="false" 
                        class="comparison-progressbar"
                    ></ProgressBar>
                    
                    <template #popper>
                        <div class="flex flex-col gap-2 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-sm" :style="{ backgroundColor: progressBarColor }"></div>
                                <span>{{ trans('Historic') }}: {{ locale.currencyFormat(currencyCode?.code, historicCLV) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-gray-200 rounded-sm"></div>
                                <span>{{ trans('Predicted') }}: {{ locale.currencyFormat(currencyCode?.code, predictedCLV) }}</span>
                            </div>
                        </div>
                    </template>
                </Tooltip>
            </div>

            <div class="space-y-2">
                <div v-if="data?.expected_date_of_next_order" class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Expected date of next order') }}</span>
                    <span>{{ useFormatTime(data?.expected_date_of_next_order, { formatTime: 'MMM dd, yyyy' }) }}</span>
                </div>
                <div v-if="data?.average_time_between_orders" class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Avg time between orders') }}</span>
                    <span>{{ data?.average_time_between_orders }} {{ trans('days') }}</span>
                </div>
                <div v-if="data?.average_order_value" class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Avarage order value') }}</span>
                    <span>{{ locale.currencyFormat(currencyCode?.code, data.average_order_value || 0) }}</span>
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
