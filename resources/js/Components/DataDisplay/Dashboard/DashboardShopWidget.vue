<script setup lang="ts">
    import { inject } from "vue";
    import { trans } from "laravel-vue-i18n";
    import ShopSales from "@/Components/Shop/ShopSales.vue";
    import ShopInvoices from "@/Components/Shop/ShopInvoices.vue";
    import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";

    const props = defineProps<{
        interval: string
        data: any
    }>()

    const locale = inject('locale', aikuLocaleStructure);

    const getAverageOrderValue = () => {
        const sales = props.data.interval_data.sales_org_currency?.[props.interval]?.raw_value;
        const orders = props.data.interval_data.orders?.[props.interval]?.raw_value;

        if (!sales || !orders || orders === 0) return null;

        return locale.currencyFormat(props.data.currency_code, sales / orders);
    }

    const getConversionRate = () => {
        const orders = props.data.interval_data.orders?.[props.interval]?.raw_value;
        const visitors = props.data.interval_data.visitors?.[props.interval]?.raw_value;

        if (!orders || !visitors || visitors === 0) return null;

        return (orders / visitors) * 100;
    }

    const getYoYComparison = (metric: string) => {
        const delta = props.data.interval_data[`${metric}_delta`]?.[props.interval];
        if (!delta || delta.raw_value === 9999999) return null;

        return {
            value: delta.formatted_value,
            isPositive: delta.raw_value > 1,
            isNegative: delta.raw_value < 1
        };
    }

    console.log("Shop Props: ", props)
</script>

<template>
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 p-4">
        <ShopSales :interval="interval" :data="data.interval_data" />
        <ShopInvoices :interval="interval" :data="data.interval_data" />
        <div class="flex items-center gap-4 h-32 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">{{ trans('Registrations') }}</p>
                <span class="text-2xl font-bold">
                    {{ data.interval_data.registrations?.[interval]?.formatted_value || '0' }}
                    <span v-if="getYoYComparison('registrations')" :class="['italic text-base font-medium ml-1', { 'text-green-500': getYoYComparison('registrations')?.isPositive, 'text-red-500': getYoYComparison('registrations')?.isNegative }]">
                        {{ getYoYComparison('registrations')?.value }}
                    </span>
                </span>
                <p class="text-xs text-gray-500 mt-1">{{ trans('New customer registrations') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-4 h-32 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">{{ trans('Purchases') }}</p>
                <span class="text-2xl font-bold">
                    {{ data.interval_data.orders?.[interval]?.formatted_value || '0' }}
                    <span v-if="getYoYComparison('orders')" :class="['italic text-base font-medium ml-1', { 'text-green-500': getYoYComparison('orders')?.isPositive, 'text-red-500': getYoYComparison('orders')?.isNegative }]">
                        {{ getYoYComparison('orders')?.value }}
                    </span>
                </span>
                <p class="text-xs text-gray-500 mt-1">{{ trans('Total orders') }}</p>
            </div>
        </div>

        <div v-if="getAverageOrderValue() !== null" class="flex items-center gap-4 h-32 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">{{ trans('Average Order Value') }}</p>
                <span class="text-2xl font-bold">{{ getAverageOrderValue() }}</span>
                <p class="text-xs text-gray-500 mt-1">{{ trans('Total sales ÷ Number of orders') }}</p>
            </div>
        </div>

        <div v-if="getConversionRate() !== null" class="flex items-center gap-4 h-32 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">{{ trans('Conversion Rate') }}</p>
                <span class="text-2xl font-bold">{{ getConversionRate().toFixed(2) }}%</span>
                <p class="text-xs text-gray-500 mt-1">{{ trans('Orders ÷ Total visits') }}</p>
            </div>
        </div>
    </div>
</template>
