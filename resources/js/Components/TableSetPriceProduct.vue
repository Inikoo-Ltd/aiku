<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { InputNumber } from "primevue";
import { inject } from "vue";

// Interfaces
interface TradeUnit {
    id: number;
    name: string;
    code?: string;
    image?: {
        thumbnail: string;
    };
}

interface ProductItem {
    id: number;
    name: string;
    code?: string;
    shop_id: number;
    shop_name: string;
    shop_currency: string;
    price: number | string;
    update_route: {
        name: string;
        parameters: Record<string, any>;
    };
    stock?: number;
    create_webpage?: boolean;
    currency?: string;
}

interface ProductData {
    id: number;
    name: string;
    image?: {
        source: string;
    };
    trade_units: TradeUnit[];
    data: ProductItem[];
}

// 👇 defineModel makes `v-model:data` available
const modelValue = defineModel<ProductData>();

const props = defineProps<{
    currency: string;
    master_price: number;
}>();

const locale = inject("locale", {});
</script>

<template>
    <div class="bg-white border rounded-md shadow-sm p-3">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                Products ({{ modelValue.data.length }})
            </h3>
            <span class="px-2 py-0.5 text-[11px] rounded bg-gray-100 text-gray-600 border border-gray-200">
                Master Price:
                {{ locale.currencyFormat(currency || "usd", props.master_price) }}
            </span>
        </div>

        <div v-if="modelValue.data.length" class="overflow-x-auto">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 text-left font-medium text-gray-600 border-b border-gray-200">
                        <th class="px-2 py-1">Code</th>
                        <th class="px-2 py-1">Name</th>
                        <th class="px-2 py-1">Stock</th>
                        <th class="px-2 py-1">
                            <div class="flex justify-center items-center">Set Webpage</div>
                        </th>
                        <th class="px-2 py-1">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in modelValue.data" :key="item.id" class="transition-colors">
                        <td class="px-2 py-1 border-b border-gray-100 text-gray-600">
                            {{ item.code || "-" }}
                        </td>
                        <td class="px-2 py-1 border-b border-gray-100 font-medium text-gray-700">
                            {{ item.name }}
                        </td>
                        <td class="px-2 py-1 border-b border-gray-100 font-medium text-gray-700">
                            {{ item.product.stock }}
                        </td>
                        <td class="px-2 py-1 border-b border-gray-100">
                            <div class="flex justify-center items-center">
                                <input type="checkbox" v-model="item.product.create_webpage" />
                            </div>
                        </td>
                        <td class="px-2 py-1 border-b w-32">
                            <InputNumber
                                v-model="item.product.price"
                                mode="currency"
                                :currency="item.currency"
                                :step="0.25"
                                :showButtons="true"
                                inputClass="w-full text-xs"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="text-xs text-gray-500 italic p-4 text-center bg-gray-50 rounded">
            {{ trans("No data available") }}
        </div>
    </div>
</template>
