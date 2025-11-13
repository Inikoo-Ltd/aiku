<script setup lang="ts">
import { inject, computed, ref, defineProps } from "vue";
import { trans } from "laravel-vue-i18n"
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

const isShowDetail = ref(false)

const timelineData = computed(() => ({
    todayPosition: props.data?.today_timeline_position || 0,
    nextOrderPosition: props.data?.next_order_timeline_position,
    hasNextOrder: props.data?.next_order_timeline_position !== null && props.data?.next_order_timeline_position <= 100
}))

// Tooltips
const historicTooltip = computed(() => {
    const amount = locale.currencyFormat(props.currencyCode?.code, props.data?.historic_clv_amount || 0)
    const orders = props.data?.number_orders || 0
    const firstDate = props.data?.first_order_date ? useFormatTime(props.data.first_order_date, { formatTime: 'MMM dd, yyyy' }) : 'N/A'

    return trans('Historic CLV: :amount from :orders orders since :date', {
        amount,
        orders,
        date: firstDate
    })
})

const predictedTooltip = computed(() => {
    const nextYearAmount = locale.currencyFormat(props.currencyCode?.code, props.data?.predicted_clv_amount_next_year || 0)
    const lifespanAmount = locale.currencyFormat(props.currencyCode?.code, props.data?.predicted_clv_amount || 0)

    return trans('Predicted CLV next year: :nextYearAmount\nPredicted CLV lifespan: :lifespanAmount', {
        nextYearAmount,
        lifespanAmount
    })
})

const todayTooltip = computed(() => {
    return trans('Today: :date', { date: useFormatTime(new Date(), { formatTime: 'MMM dd, yyyy' }) })
})

const nextOrderTooltip = computed(() => {
    if (!props.data?.expected_date_of_next_order) return trans('No expected order date available')

    const date = useFormatTime(props.data.expected_date_of_next_order, { formatTime: 'MMM dd, yyyy' })
    const diffDays = Math.round((new Date(props.data.expected_date_of_next_order).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24))

    if (diffDays > 0) {
        return trans('Expected to place next order in :days days on :date', {
            days: diffDays,
            date
        })
    } else if (diffDays === 0) {
        return trans('Expected to place next order today: :date', { date })
    } else {
        return trans('Should have placed order :days days ago on :date', {
            days: Math.abs(diffDays),
            date
        })
    }
})

// Calculate one year from now for display
const oneYearFromNow = computed(() => {
    const date = new Date()
    date.setFullYear(date.getFullYear() + 1)
    return date
})
</script>

<template>
    <div :class="['border rounded-lg p-4', { 'hidden': !data?.total_clv_amount }]">
        <div class="flex flex-col gap-2">
            <div class="box-border">
                <h3 class="text-lg font-bold">{{ locale.currencyFormat(currencyCode?.code, data?.total_clv_amount || 0) }}
                </h3>
                <span class="text-sm">{{ trans('Customer Lifetime Value') }} (CLV)</span>
            </div>

            <!-- Timeline Progress Bar -->
            <div class="mb-2">
                <div class="relative h-8 bg-gray-100 rounded-lg overflow-hidden">
                    <!-- Historic Timeline -->
                    <Tooltip placement="top">
                        <div
                            class="absolute left-0 top-0 h-full bg-blue-500 transition-all duration-300"
                            :style="{ width: `${timelineData.todayPosition}%` }"
                        ></div>
                        <template #popper>
                            <div class="text-xs">
                                {{ historicTooltip }}
                            </div>
                        </template>
                    </Tooltip>

                    <!-- Predicted Timeline -->
                    <Tooltip placement="top">
                        <div
                            class="absolute right-0 top-0 h-full bg-green-500 transition-all duration-300"
                            :style="{ width: `${100 - timelineData.todayPosition}%`, left: `${timelineData.todayPosition}%` }"
                        ></div>
                        <template #popper>
                            <div class="text-xs whitespace-pre-line">
                                {{ predictedTooltip }}
                            </div>
                        </template>
                    </Tooltip>

                    <!-- Today Indicator -->
                    <Tooltip placement="top">
                        <div
                            class="absolute top-0 bottom-0 w-0.5 bg-black -ml-0.5 z-10"
                            :style="{ left: `${timelineData.todayPosition}%` }"
                        >
                            <div class="absolute -top-2 -left-1 w-2 h-2 bg-black rounded-full"></div>
                        </div>
                        <template #popper>
                            <div class="text-xs text-center">
                                <div class="font-semibold">{{ trans('Today') }}</div>
                                <div>{{ todayTooltip }}</div>
                            </div>
                        </template>
                    </Tooltip>

                    <!-- Next Order Indicator -->
                    <Tooltip placement="top" v-if="timelineData.hasNextOrder && timelineData.nextOrderPosition">
                        <div
                            class="absolute -top-1 -ml-2 w-4 h-4 bg-red-500 rotate-45 transform z-20 border border-white"
                            :style="{ left: `${timelineData.nextOrderPosition}%` }"
                        ></div>
                        <template #popper>
                            <div class="text-xs text-center">
                                <div class="font-semibold">{{ trans('Expected Next Order') }}</div>
                                <div>{{ nextOrderTooltip }}</div>
                            </div>
                        </template>
                    </Tooltip>
                </div>

                <!-- Timeline Labels -->
                <div class="flex justify-between text-xs mt-2 px-1">
                    <div class="text-left">
                        <div class="font-semibold">{{ trans('First Order') }}</div>
                        <div v-if="data?.first_order_date">
                            {{ useFormatTime(data.first_order_date, { formatTime: 'MMM dd, yyyy' }) }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="font-semibold">{{ trans('Today') }}</div>
                        <div>{{ useFormatTime(new Date(), { formatTime: 'MMM dd, yyyy' }) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold">{{ trans('+1 Year') }}</div>
                        <div>{{ useFormatTime(oneYearFromNow, { formatTime: 'MMM dd, yyyy' }) }}</div>
                    </div>
                </div>

                <!-- CLV Values -->
                <div class="flex justify-between text-xs mt-2">
                    <div class="text-left">
                        <div class="font-semibold text-blue-600">
                            {{ locale.currencyFormat(currencyCode?.code, data?.historic_clv_amount || 0) }}
                        </div>
                        <div>{{ data?.number_orders || 0 }} {{ trans('orders') }}</div>
                    </div>
                    <Tooltip placement="top">
                        <div class="text-right cursor-help">
                            <div class="font-semibold text-green-600">
                                {{ locale.currencyFormat(currencyCode?.code, data?.predicted_clv_amount_next_year || 0) }}
                            </div>
                            <div>{{ trans('Next Year') }}</div>
                        </div>
                        <template #popper>
                            <div class="text-xs whitespace-pre-line">
                                {{ predictedTooltip }}
                            </div>
                        </template>
                    </Tooltip>
                </div>
            </div>

            <!-- Additional Metrics -->
            <div class="space-y-2">
                <div v-if="data?.churn_risk_prediction !== undefined" class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Churn Risk Prediction') }}</span>
                    <span :class="{
                        'text-green-600': data.churn_risk_prediction < 0.3,
                        'text-yellow-600': data.churn_risk_prediction >= 0.3 && data.churn_risk_prediction < 0.7,
                        'text-red-600': data.churn_risk_prediction >= 0.7
                    }">
                        {{ (Number(data.churn_risk_prediction) * 100).toFixed(0) }}%
                    </span>
                </div>
                <div v-if="data?.average_time_between_orders" class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Avg time between orders') }}</span>
                    <span>{{ data?.average_time_between_orders }} {{ trans('days') }}</span>
                </div>
                <div v-if="data?.average_order_value" class="flex justify-between text-xs">
                    <span class="font-semibold">{{ trans('Average order value') }}</span>
                    <span>{{ locale.currencyFormat(currencyCode?.code, data.average_order_value || 0) }}</span>
                </div>
                <div v-if="data?.expected_date_of_next_order" class="flex justify-between text-xs">
                    <Tooltip placement="top">
                        <span class="font-semibold underline decoration-dotted cursor-help">{{ trans('Expected next order') }}</span>
                        <template #popper>
                            <span class="text-xs">{{ nextOrderTooltip }}</span>
                        </template>
                    </Tooltip>
                    <span>{{ useFormatTime(data?.expected_date_of_next_order, { formatTime: 'MMM dd, yyyy' }) }}</span>
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
::v-deep(.floating-vue-tooltip) {
    z-index: 10000;
}

.rotate-45 {
    z-index: 20;
    box-shadow: 0 0 2px rgba(0,0,0,0.5);
}
</style>
