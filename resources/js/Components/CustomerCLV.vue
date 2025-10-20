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

const isShowDetail = ref(false)

const historicPercentage = computed(() => {
    const expectedDate = props.data?.expected_date_of_next_order
    if (!expectedDate) return 0

    const dateObj = new Date(expectedDate)
    if (isNaN(dateObj)) return 0

    const diffDays = (dateObj - new Date()) / (1000 * 60 * 60 * 24)

    let percentage = 0

    if (diffDays >= 40) {
        // Still far in the future → full blue
        percentage = 100
    } else if (diffDays >= 0) {
        // In the next 0–40 days → getting closer, the bar is decreasing
        percentage = (diffDays / 40) * 100
    } else if (diffDays >= -40) {
        // It has been more than 0–40 days → the red bar keeps rising
        percentage = ((Math.abs(diffDays)) / 40) * 100
    } else {
        // More than 40 days overdue → full red
        percentage = 100
    }

    return percentage
})

const progressBarColor = computed(() => {
    const expectedDate = props.data?.expected_date_of_next_order
    if (!expectedDate) return layout?.app?.theme[0]

    const dateObj = new Date(expectedDate)
    if (isNaN(dateObj)) return layout?.app?.theme[0]

    const diffDays = (dateObj - new Date()) / (1000 * 60 * 60 * 24)

    // If it's past today → red
    if (diffDays < 0) {
        return "#FF0000"
    }

    // Still ahead → blue (or default color)
    return layout?.app?.theme[0]
})

const expectedOrderTooltip = computed(() => {
    const expectedDate = props.data?.expected_date_of_next_order
    if (!expectedDate) return trans("No expected order date available")

    const dateObj = new Date(expectedDate)
    if (isNaN(dateObj)) return trans("Invalid date")

    const diffDays = Math.round((dateObj - new Date()) / (1000 * 60 * 60 * 24))

    if (diffDays > 0) {
        return trans("Customer expected to place an order in :days days", { days: diffDays })
    } else if (diffDays === 0) {
        return trans("Customer expected to place an order today")
    } else {
        return trans("Customer should have placed an order :days days ago", { days: Math.abs(diffDays) })
    }
})
</script>

<template>
    <div :class="['border rounded-lg p-4', { 'hidden': !data.total_clv_amount }]">
        <div class="flex flex-col gap-2">
            <div class="box-border">
                <h3 class="text-lg font-bold">{{ locale.currencyFormat(currencyCode?.code, data?.total_clv_amount || 0) }}
                </h3>
                <span class="text-sm">{{ trans('Customer Lifetime Value') }} (CLV)</span>
            </div>

            <!-- Progress Bar Comparison using PrimeVue -->
            <div :class="['mb-2 hover:ring-1 p-[2px] cursor-pointer rounded-md', { 'hidden': !historicPercentage  }]" @click="isShowDetail = true">
                <!-- Single Progress Bar with Tooltip -->
                <Tooltip placement="top">
                    <ProgressBar
                        :value="historicPercentage"
                        :showValue="false"
                        class="comparison-progressbar"
                    ></ProgressBar>

                    <template #popper>
                        <span class="text-xs">{{ expectedOrderTooltip }}</span>
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
                    <span class="font-semibold">{{ trans('Average order value') }}</span>
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
