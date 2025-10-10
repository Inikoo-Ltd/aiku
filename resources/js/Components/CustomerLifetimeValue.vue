<script setup lang="ts">
import { ref, computed, defineProps } from 'vue'

interface CustomerLifetimeValueData {
    average_order_value: string
    average_time_between_orders: string
    churn_interval: string
    churn_risk_prediction: string
    created_at: string
    customer_id: number
    expected_date_of_next_order: string
    historic_clv_amount: string
    historic_clv_amount_grp_currency: string
    historic_clv_amount_org_currency: string
    id: number
}

const props = defineProps<{
    data: CustomerLifetimeValueData
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

// Sample data untuk orders
const orders = ref<Order[]>([
    { date: '2016-09-17', value: 45, churnProb: 0.2 },
    { date: '2016-10-15', value: 120, churnProb: 0.4 },
    { date: '2016-11-20', value: 85, churnProb: 0.3 },
    { date: '2017-01-10', value: 95, churnProb: 0.5 },
    { date: '2017-02-28', value: 60, churnProb: 0.2 },
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
const historicOrders = computed(() => orders.value.filter(o => !o.predicted))
const predictedOrders = computed(() => orders.value.filter(o => o.predicted))

const historicCLV = computed(() => parseFloat(props.data.historic_clv_amount || '0'))

const predictedCLV = computed(() =>
    predictedOrders.value.reduce((sum, o) => sum + o.value, 0)
)

const totalCLV = computed(() => historicCLV.value + predictedCLV.value)

const avgOrderValue = computed(() => parseFloat(props.data.average_order_value || '0'))

// Calculate average time between orders from props (convert to days)
const avgTimeBetweenOrders = computed(() => {
    const avgMonths = parseFloat(props.data.average_time_between_orders || '0')
    return Math.round(avgMonths * 30.44) // Convert months to days
})

// Churn risk prediction from props (convert to percentage)
const churnRiskPrediction = computed(() => {
    const risk = parseFloat(props.data.churn_risk_prediction || '0')
    return (risk * 100).toFixed(0) // Convert to percentage
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
                <h2 class="text-xl font-semibold text-gray-700">PREDICTIVE ANALYTICS</h2>
            </div>
            <slot name="close"></slot>
        </div>

        <h3 class="text-sm text-gray-600 mb-6">Customer Lifetime Value (CLV)</h3>

        <!-- CLV Values -->
        <div class="flex items-start justify-center gap-12 mb-6">
            <div class="text-center">
                <div class="text-sm text-gray-500 mb-1">Historic CLV</div>
                <div class="text-2xl font-semibold text-gray-800">${{ historicCLV.toFixed(0) }}</div>
                <div class="text-xs text-gray-400">{{ historicOrders.length }} orders</div>
            </div>
            <div class="text-3xl text-gray-300 self-center">+</div>
            <div class="text-center">
                <div class="text-sm text-gray-500 mb-1">Predicted CLV</div>
                <div class="text-2xl font-semibold text-gray-800">${{ predictedCLV.toFixed(0) }}</div>
                <div class="text-xs text-gray-400">{{ predictedOrders.length }} orders</div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="relative mb-6">
            <div class="h-12 bg-gray-100 rounded relative overflow-hidden" id="wrapper">
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
                    <span>Avg Purchase Value: ${{ hoveredOrder.avgPurchaseValue.toFixed(2) }} ({{
                        hoveredOrder.valuePercent.toFixed(1) }}%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-yellow-500 rounded-full"></div>
                    <span>Avg Frequency: {{ hoveredOrder.avgPurchaseFrequency.toFixed(2) }}/mo ({{
                        hoveredOrder.frequencyPercent.toFixed(1) }}%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-red-500 rounded-full"></div>
                    <span>Avg Lifespan: {{ hoveredOrder.avgCustomerLifespan.toFixed(1) }} mo ({{
                        hoveredOrder.lifespanPercent.toFixed(1) }}%)</span>
                </div>
                <div v-if="hoveredOrder.predicted" class="text-blue-300 mt-1 text-center">Predicted</div>
            </div>

            <!-- Timeline labels -->
            <div class="flex justify-between mt-2 text-xs text-gray-500">
                <span>Sep 17, 2016</span>
                <span :style="{ position: 'relative', left: (todayPosition - 50) + '%' }">Today</span>
                <span>+1 Year</span>
            </div>
        </div>

        <!-- Churn probability legend -->
        <div class="flex items-center justify-center gap-3 mb-6">
            <span class="text-xs text-gray-500">Churn Probability:</span>
            <div class="flex items-center gap-1">
                <span class="text-xs text-gray-500">Low</span>
                <div class="w-32 h-3 rounded"
                    style="background: linear-gradient(to right,  #A3FFC3, #EBE571,#FF8B42)" />
                <span class="text-xs text-gray-500">High</span>
            </div>
        </div>

        <!-- Statistics -->
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Total CLV</span>
                <span class="font-medium text-gray-800">${{ totalCLV.toFixed(0) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Churn Risk Prediction</span>
                <span class="font-medium text-gray-800">{{ churnRiskPrediction }}%</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Average Time Between Orders</span>
                <span class="font-medium text-gray-800">{{ avgTimeBetweenOrders }} days</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Average Order Value</span>
                <span class="font-medium text-gray-800">${{ avgOrderValue.toFixed(2) }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Additional styles if needed */
</style>