<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { InputNumber } from "primevue";
import { inject, reactive } from "vue";

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

const props = defineProps<{
    currency: string;
    data: ProductData;
    master_price: number;
}>();

const locale = inject("locale", {});

// make productStates indexed by id
/* const productStates = reactive(
    props.data.data.reduce((acc, item) => {
        acc[item.id] = {
            product : {
                price: item.product.price,
                create_webpage: item.product.create_webpage ?? false,
            }
        };
        return acc;
    }, {} as Record<number, { price: number | string; create_webpage: boolean }>)
); */
</script>

<template>
    <!-- Products (compact table) -->
    <div class="bg-white border rounded-md shadow-sm p-3">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                Products ({{ data.data.length }})
            </h3>
            <span class="px-2 py-0.5 text-[11px] rounded bg-gray-100 text-gray-600 border border-gray-200">
                Master Price:
                {{ locale.currencyFormat(data.currency || "usd", master_price) }}
            </span>
        </div>

        <div v-if="data.data.length" class="overflow-x-auto">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 text-left font-medium text-gray-600 border-b border-gray-200">
                        <th class="px-2 py-1">Code</th>
                        <th class="px-2 py-1">Name</th>
                        <th class="px-2 py-1">Stock</th>
                        <th class="px-2 py-1">
                            <div class="flex justify-center items-center">
                                Set Webpage
                            </div>
                        </th>
                        <th class="px-2 py-1">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in data.data" :key="item.id" class="transition-colors">
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
                                <input type="checkbox" :key="item.id" v-model="item.product.create_webpage" />
                            </div>
                        </td>
                        <td class="px-2 py-1 border-b w-32">
                            <InputNumber v-model="item.product.price" mode="currency" :key="item.id"
                                :currency="item.currency" :step="0.25" :showButtons="true"
                                inputClass="w-full text-xs" />
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
