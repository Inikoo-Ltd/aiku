<script setup lang="ts">
import { ref, computed, defineProps, inject } from 'vue'
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { Tooltip } from 'floating-vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestionCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"

library.add(faQuestionCircle)

interface CustomerLifetimeValueData {
    id: number
    customer_id: number
    sales_all: string
    sales_org_currency_all: string
    sales_grp_currency_all: string
    last_order_created_at: string | null
    last_order_submitted_at: string | null
    last_order_dispatched_at: string | null
    number_orders: number
    number_orders_state_creating: number
    number_orders_state_submitted: number
    number_orders_state_in_warehouse: number
    number_orders_state_handling: number
    number_orders_state_handling_blocked: number
    number_orders_state_packed: number
    number_orders_state_finalised: number
    number_orders_state_dispatched: number
    number_orders_state_cancelled: number
    number_orders_status_creating: number
    number_orders_status_processing: number
    number_orders_status_settled: number
    number_orders_handing_type_collection: number
    number_orders_handing_type_shipping: number
    number_item_transactions_out_of_stock_in_basket: number
    out_of_stock_in_basket_grp_net_amount: string | null
    out_of_stock_in_basket_org_net_amount: string | null
    out_of_stock_in_basket_net_amount: string
    number_item_transactions: number
    number_current_item_transactions: number
    number_item_transactions_state_creating: number
    number_item_transactions_state_submitted: number
    number_item_transactions_state_in_warehouse: number
    number_item_transactions_state_handling: number
    number_item_transactions_state_packed: number
    number_item_transactions_state_finalised: number
    number_item_transactions_state_dispatched: number
    number_item_transactions_state_cancelled: number
    number_item_transactions_status_creating: number
    number_item_transactions_status_processing: number
    number_item_transactions_status_settled: number
    number_invoices: number
    number_invoices_type_invoice: number
    number_invoices_type_refund: number
    last_invoiced_at: string
    number_invoice_transactions: number
    number_positive_invoice_transactions: number
    number_negative_invoice_transactions: number
    number_zero_invoice_transactions: number
    number_current_invoice_transactions: number
    number_positive_current_invoice_transactions: number
    number_negative_current_invoice_transactions: number
    number_zero_current_invoice_transactions: number
    number_invoiced_customers: number
    last_delivery_note_created_at: string
    last_delivery_note_dispatched_at: string
    last_delivery_note_type_order_created_at: string
    last_delivery_note_type_order_dispatched_at: string
    last_delivery_note_type_replacement_created_at: string | null
    last_delivery_note_type_replacement_dispatched_at: string | null
    number_delivery_notes: number
    number_delivery_notes_type_order: number
    number_delivery_notes_type_replacement: number
    number_delivery_notes_state_unassigned: number
    number_delivery_notes_state_queued: number
    number_delivery_notes_state_handling: number
    number_delivery_notes_state_handling_blocked: number
    number_delivery_notes_state_packed: number
    number_delivery_notes_state_finalised: number
    number_delivery_notes_state_dispatched: number
    number_delivery_notes_state_cancelled: number
    number_delivery_notes_cancelled_at_state_unassigned: number
    number_delivery_notes_cancelled_at_state_queued: number
    number_delivery_notes_cancelled_at_state_handling: number
    number_delivery_notes_cancelled_at_state_handling_blocked: number
    number_delivery_notes_cancelled_at_state_packed: number
    number_delivery_notes_cancelled_at_state_finalised: number
    number_delivery_notes_cancelled_at_state_dispatched: number
    number_delivery_notes_state_with_out_of_stock: number
    number_delivery_note_items: number
    number_uphold_delivery_note_items: number
    number_delivery_note_items_state_unassigned: number
    number_delivery_note_items_state_queued: number
    number_delivery_note_items_state_handling: number
    number_delivery_note_items_state_handling_blocked: number
    number_delivery_note_items_state_packed: number
    number_delivery_note_items_state_finalised: number
    number_delivery_note_items_state_dispatched: number
    number_delivery_note_items_state_cancelled: number
    number_web_users: number
    number_current_web_users: number
    number_web_users_type_web: number
    number_web_users_type_api: number
    number_web_users_auth_type_default: number
    number_web_users_auth_type_aurora: number
    number_customer_clients: number
    number_current_customer_clients: number
    number_portfolios: number
    number_current_portfolios: number
    number_credit_transactions: number
    number_top_ups: number
    number_top_ups_status_in_process: number
    number_top_ups_status_success: number
    number_top_ups_status_fail: number
    number_favourites: number
    number_unfavourited: number
    number_reminders: number
    number_reminders_cancelled: number
    created_at: string
    updated_at: string
    number_unpaid_invoices: number
    unpaid_invoices_amount: string
    unpaid_invoices_amount_org_currency: string
    unpaid_invoices_amount_grp_currency: string
    number_deleted_invoices: number
    number_platforms: number
    number_customer_sales_channels: number
    number_customer_sales_channels_platform_type_shopify: number
    number_customer_sales_channels_platform_type_tiktok: number
    number_customer_sales_channels_platform_type_woocommerce: number
    number_customer_sales_channels_platform_type_ebay: number
    number_customer_sales_channels_platform_type_manual: number
    number_customer_sales_channels_platform_type_amazon: number
    number_customer_sales_channels_platform_type_magento: number
    number_customer_sales_channels_platform_type_wix: number
    historic_clv_amount: string
    historic_clv_amount_org_currency: string
    historic_clv_amount_grp_currency: string
    predicted_clv_amount: string
    predicted_clv_amount_org_currency: string
    predicted_clv_amount_grp_currency: string
    total_clv_amount: string
    total_clv_amount_org_currency: string
    total_clv_amount_grp_currency: string
    churn_interval: string
    churn_risk_prediction: string
    average_time_between_orders: string
    average_order_value: string
    expected_date_of_next_order: string
}

const props = defineProps<{
    data: CustomerLifetimeValueData
    currencyCode: {}
}>()


interface Order {
    date: string
    value: number
    churnProb: number
    predicted?: boolean
}

interface OrderBlock {
    date: string
    value: number
    churnProb: number
    predicted?: boolean
    left: number
    width: number
    avgPurchaseValue: number
    avgPurchaseFrequency: number
    avgCustomerLifespan: number
    valuePercent: number
    frequencyPercent: number
    lifespanPercent: number
}

const locale = inject('locale', aikuLocaleStructure)

// Sample data untuk orders
const orders = ref<Order[]>([
    { date: '2016-09-17', value: 45, churnProb: 0.2 },
    { date: '2016-10-15', value: 120, churnProb: 0.4 },
    { date: '2016-11-20', value: 85, churnProb: 0.3 },
    { date: '2017-01-10', value: 95, churnProb: 0.5 },
    { date: '2017-02-28', value: 6000, churnProb: 0.2 },
    // { date: '2017-04-15', value: 150, churnProb: 0.6 },
    // { date: '2017-06-05', value: 75, churnProb: 0.3 },
    // { date: '2017-08-22', value: 110, churnProb: 0.4 },
    // { date: '2017-10-10', value: 88, churnProb: 0.5 },
    // { date: '2017-12-01', value: 95, churnProb: 0.2 },
    // { date: '2018-01-20', value: 70, churnProb: 0.3 },
    // { date: '2018-03-15', value: 125, churnProb: 0.4 },
    // { date: '2018-05-08', value: 92, churnProb: 0.3 },
    // { date: '2018-07-12', value: 105, churnProb: 0.2 },
    // { date: '2018-09-25', value: 88, churnProb: 0.5 },
    // { date: '2018-11-30', value: 115, churnProb: 0.6 },
    // { date: '2019-02-14', value: 78, churnProb: 0.4 },
    // Predicted orders
    { date: '2019-04-10', value: 95, churnProb: 0.3, predicted: true },
    { date: '2019-06-20', value: 82, churnProb: 0.4, predicted: true },
    { date: '2019-08-15', value: 105, churnProb: 0.5, predicted: true },
    { date: '2019-10-05', value: 90, churnProb: 0.6, predicted: true },
    { date: '2019-12-20', value: 88, churnProb: 0.4, predicted: true },
])

const startDate = new Date('2016-09-17')
const endDate = new Date('2020-02-14')
const todayDate = new Date('2019-02-14')

const totalDays = (endDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24)

const todayPosition = computed(() =>
    ((todayDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24) / totalDays) * 100
)

// Calculate statistics using props data
const historicCLV = computed(() => parseFloat(props?.data?.historic_clv_amount || '0'))

const predictedCLV = computed(() => parseFloat(props?.data?.predicted_clv_amount || '0'))

const totalCLV = computed(() => parseFloat(props?.data?.total_clv_amount || '0'))

const avgOrderValue = computed(() => parseFloat(props?.data?.average_order_value || '0'))

// Calculate average time between orders from props (convert to days)
const avgTimeBetweenOrders = computed(() => {
    const avgMonths = parseFloat(props?.data?.average_time_between_orders || '0')
    return avgMonths // Convert months to days
})

// Churn risk prediction from props (already in percentage format)
const churnRiskPrediction = computed(() => {
    const risk = parseFloat(props?.data?.churn_risk_prediction || '0')
    return risk.toFixed(0)
})

// Get gradient color based on churn probability
const getGradientColor = (churnProb: number): string => {
    // Create smooth gradient from green to red
    const r = Math.round(74 + (churnProb * 165)) // 74 to 239
    const g = Math.round(222 - (churnProb * 138)) // 222 to 84
    const b = Math.round(128 - (churnProb * 44)) // 128 to 84
    return `rgb(${r}, ${g}, ${b})`
}

const getPosition = (date: string): number => {
    const orderDate = new Date(date)
    const position = ((orderDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24) / totalDays) * 100
    return position
}

const hoveredOrder = ref<OrderBlock | null>(null)

// Calculate dynamic widths for order blocks with CLV components
const orderBlocks = computed((): OrderBlock[] => {
    const sortedOrders = [...orders.value].sort((a, b) => 
        new Date(a.date).getTime() - new Date(b.date).getTime()
    )
    
    return sortedOrders.map((order, idx) => {
        const currentDate = new Date(order.date)
        const currentPosition = ((currentDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24) / totalDays) * 100
        
        // Calculate block width
        let width: number
        if (idx < sortedOrders.length - 1) {
            const nextDate = new Date(sortedOrders[idx + 1].date)
            const nextPosition = ((nextDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24) / totalDays) * 100
            width = nextPosition - currentPosition
        } else {
            width = 100 - currentPosition
        }
        
        // Calculate CLV components for this order
        const ordersUpToNow = sortedOrders.slice(0, idx + 1)
        
        // 1. Average Purchase Value (total value / number of orders)
        const totalValue = ordersUpToNow.reduce((sum, o) => sum + o.value, 0)
        const avgPurchaseValue = totalValue / ordersUpToNow.length
        
        // 2. Average Purchase Frequency (orders per month)
        const firstOrderDate = new Date(sortedOrders[0].date)
        const monthsElapsed = ((currentDate.getTime() - firstOrderDate.getTime()) / (1000 * 60 * 60 * 24 * 30.44)) || 1
        const avgPurchaseFrequency = ordersUpToNow.length / monthsElapsed
        
        // 3. Average Customer Lifespan (in months)
        const avgCustomerLifespan = monthsElapsed
        
        // Normalize values to make them more balanced for visualization
        // Scale Purchase Value down (divide by 10) and scale Frequency up (multiply by 20)
        const normalizedValue = avgPurchaseValue / 10
        const normalizedFrequency = avgPurchaseFrequency * 20
        const normalizedLifespan = avgCustomerLifespan
        
        // Calculate proportions (percentages)
        const total = normalizedValue + normalizedFrequency + normalizedLifespan
        const valuePercent = (normalizedValue / total) * 100
        const frequencyPercent = (normalizedFrequency / total) * 100
        const lifespanPercent = (normalizedLifespan / total) * 100
        
        return {
            ...order,
            left: currentPosition,
            width: width,
            avgPurchaseValue,
            avgPurchaseFrequency,
            avgCustomerLifespan,
            valuePercent,
            frequencyPercent,
            lifespanPercent
        }
    })
})

// Get the position where predicted orders start
const predictedStartPosition = computed(() => {
    const firstPredicted = orders.value.find(o => o.predicted)
    if (!firstPredicted) return 100
    return getPosition(firstPredicted.date)
})
</script>

<template>
    <div class="w-full max-w-5xl mx-auto p-6 bg-white rounded-lg">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-semibold text-gray-700">{{ trans('PREDICTIVE ANALYTICS') }}</h2>
            </div>
            <slot name="close"></slot>
        </div>

        <h3 class="text-sm text-gray-600 mb-6">{{ trans('Customer Lifetime Value (CLV)') }}</h3>

        <!-- CLV Values -->
        <div class="flex items-start justify-center gap-12 mb-6">
            <div class="text-center">
                <div class="text-sm text-gray-500 mb-1 flex items-center justify-center gap-1">
                    <span>{{ trans('Historic CLV') }}</span>
                    <Tooltip placement="top">
                        <FontAwesomeIcon :icon="faQuestionCircle" class="text-gray-400 text-xs cursor-help" />
                        <template #popper>
                            <div class="text-xs">
                                {{ trans('Historical CLV = Average Purchase Value × Total Order') }}
                            </div>
                        </template>
                    </Tooltip>
                </div>
                <div class="text-2xl font-semibold text-gray-800">{{ locale.currencyFormat(currencyCode?.code,
                    historicCLV.toFixed(2)) }}</div>
                <div class="text-xs text-gray-400">{{ props?.data?.number_orders_state_dispatched }} {{ trans('orders') }}</div>
            </div>

            <div class="text-3xl text-gray-300 self-center">+</div>
            <div class="text-center">
                <div class="text-sm text-gray-500 mb-1 flex items-center justify-center gap-1">
                    <span>{{ trans('Predicted CLV') }}</span>
                    <Tooltip placement="top">
                        <FontAwesomeIcon :icon="faQuestionCircle" class="text-gray-400 text-xs cursor-help" />
                        <template #popper>
                            <div class="text-xs">
                                {{ trans('Predicted CLV = Customer Value (per month) × Expected Remaining Lifespan (Months)') }}
                            </div>
                        </template>
                    </Tooltip>
                </div>
                <div class="text-2xl font-semibold text-gray-800">{{ locale.currencyFormat(currencyCode?.code,
                    predictedCLV.toFixed(2)) }}</div>
                <div class="text-xs text-gray-400">{{ trans('Predicted') }}</div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="relative mb-6">
            <div class="h-8 bg-gray-100 rounded relative overflow-hidden" id="wrapper">
                <!-- Background gray for predicted area -->
                <div class="absolute top-0 h-full bg-gray-200" :style="{
                    left: predictedStartPosition + '%',
                    width: (100 - predictedStartPosition) + '%'
                }" />

                <!-- Order blocks with 3-color smooth gradient -->
                <div v-for="(block, idx) in orderBlocks" :key="idx"
                    class="absolute top-0 h-full transition-all cursor-pointer hover:opacity-90" :style="{
                        left: block.left + '%',
                        width: block.width + '%',
                        opacity: block.predicted ? 0.6 : 1,
                        background: block.predicted ? '#ffffff' : `linear-gradient(to right, 
                            #A3FFC3,
                            #EBE571 ${block.valuePercent}%,
                            #FF8B42`
                    }" @mouseenter="hoveredOrder = block" @mouseleave="hoveredOrder = null">
                </div>

                <!-- Today marker -->
                <div class="absolute top-0 h-full w-0.5 bg-gray-700 z-10" :style="{ left: todayPosition + '%' }">
                    <div class="absolute -top-1 left-1/2 -translate-x-1/2">
                        <div class="w-3 h-3 bg-gray-700 rounded-full border-2 border-white" />
                    </div>
                </div>
            </div>

            <!-- Tooltip -->
            <div v-if="hoveredOrder"
                class="absolute -top-32 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-3 py-2 rounded shadow-lg z-20 whitespace-nowrap">
                <div class="font-semibold mb-1">{{ hoveredOrder.date }}</div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                    <span>{{ trans('Avg Purchase Value') }}: ${{ hoveredOrder.avgPurchaseValue.toFixed(2) }} ({{
                        hoveredOrder.valuePercent.toFixed(1) }}%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-yellow-500 rounded-full"></div>
                    <span>{{ trans('Avg Frequency') }}: {{ hoveredOrder.avgPurchaseFrequency.toFixed(2) }}/mo ({{
                        hoveredOrder.frequencyPercent.toFixed(1) }}%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-red-500 rounded-full"></div>
                    <span>{{ trans('Avg Lifespan') }}: {{ hoveredOrder.avgCustomerLifespan.toFixed(1) }} mo ({{
                        hoveredOrder.lifespanPercent.toFixed(1) }}%)</span>
                </div>
                <div v-if="hoveredOrder.predicted" class="text-blue-300 mt-1 text-center">{{ trans('Predicted') }}</div>
            </div>

            <!-- Timeline labels -->
            <div class="flex justify-between mt-2 text-xs text-gray-500">
                <span>Sep 17, 2016</span>
                <span :style="{ position: 'relative', left: (todayPosition - 50) + '%' }">{{ trans('Today') }}</span>
                <span>+1 {{ trans('Year') }}</span>
            </div>
        </div>

        <!-- Churn probability legend -->
        <div class="flex items-center justify-center gap-3 mb-6">
            <span class="text-xs text-gray-500">{{ trans('Churn Probability') }}:</span>
            <div class="flex items-center gap-1">
                <span class="text-xs text-gray-500">{{ trans('Low') }}</span>
                <div class="w-32 h-3 rounded"
                    style="background: linear-gradient(to right,  #A3FFC3, #EBE571,#FF8B42)" />
                <span class="text-xs text-gray-500">{{ trans('High') }}</span>
            </div>
        </div>

        <!-- Statistics -->
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">{{ trans('Total CLV') }}</span>
                <span class="font-medium text-gray-800">{{ locale.currencyFormat(currencyCode?.code,
                    totalCLV.toFixed(2)) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">{{ trans('Churn Risk Prediction') }}</span>
                <span class="font-medium text-gray-800">{{ churnRiskPrediction }}%</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">{{ trans('Average Time Between Orders') }}</span>
                <span class="font-medium text-gray-800">{{ avgTimeBetweenOrders }} {{ trans('days') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">{{ trans('Average Order Value') }}</span>
                <span class="font-medium text-gray-800">{{ locale.currencyFormat(currencyCode?.code,
                    avgOrderValue.toFixed(2)) }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Additional styles if needed */
</style>