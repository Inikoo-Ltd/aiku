<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { InputNumber } from "primevue";
import { inject, computed, watch } from "vue";

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
    product?: {
        org_cost?: number;
        org_currency?: string;
        stock?: number;
        price?: number;
        rrp?: number;
        has_org_stocks?: boolean;
        useCustomRrp?: boolean; // flag for custom RRP
    };
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

// v-model:data
const modelValue = defineModel<ProductData>();

const emits = defineEmits<{
    (e: "change", payload: { tableData: ProductItem[]; data: ProductData }): void;
}>();

const props = defineProps<{
    currency: string;
    form: any;
}>();

const locale = inject("locale", {});

// helper to calculate profit margin %
function getMargin(item: ProductItem) {
    const p = Number(item.product?.price);
    const cost = Number(item.product?.org_cost);

    if (isNaN(p) || p === 0) return 0;
    if (isNaN(cost) || cost === 0) return 100;

    return Math.round(((p - cost) / p) * 100);
}

// helper for RRP margin
function getRrpMargin(item: ProductItem) {
    const rrp = item.product?.useCustomRrp
        ? Number(item.product?.rrp)
        : Math.round(Number(item.product?.price) * 2.4);

    const cost = Number(item.product?.org_cost);

    if (isNaN(rrp) || rrp === 0) return 0;
    if (isNaN(cost) || cost === 0) return 100;

    return Math.round(((rrp - cost) / rrp) * 100);
}

// computed for "check all"
const allChecked = computed({
    get() {
        return (
            modelValue.value.data.length > 0 &&
            modelValue.value.data.every((item) => item.product?.has_org_stocks)
        );
    },
    set(val: boolean) {
        modelValue.value.data.forEach((item) => {
            if (item.product) {
                item.product.has_org_stocks = val;
            }
        });
        emits("change", modelValue.value);
    },
});

// ensure every product has flags and default rrp
modelValue.value.data.forEach((item) => {
    if (item.product) {
        if (item.product.useCustomRrp === undefined) {
            item.product.useCustomRrp = false;
        }

        // default rrp
        if (!item.product.rrp) {
            item.product.rrp = Math.round(Number(item.product.price) * 2.4);
        }

        // watcher untuk auto mode
        watch(
            () => item.product!.price,
            (newPrice) => {
                if (!item.product!.useCustomRrp) {
                    item.product!.rrp = Math.round(Number(newPrice) * 2.4);
                    emits("change", modelValue.value);
                }
            },
            { immediate: true }
        );
    }
});
</script>

<template>
    <div class="bg-white border rounded-md shadow-sm p-3">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                Products ({{ modelValue.data.length }})
            </h3>
        </div>

        <div v-if="modelValue.data.length" class="overflow-x-auto">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 text-left font-medium text-gray-600 border-b border-gray-200">
                        <th class="px-2 py-1">Shop</th>
                        <th class="px-2 py-1 text-center">Stock</th>
                        <th class="px-2 py-1 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <span>Create Webpage?</span>
                                <input type="checkbox" v-model="allChecked" />
                            </div>
                        </th>
                        <th class="px-2 py-1 text-center">Org cost</th>
                        <th class="px-2 py-1">Price</th>
                        <th class="px-2 py-1 text-center">Margin</th>
                        <th class="px-2 py-1">Rrp</th>
                        <th class="px-2 py-1 text-center">Rrp Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in modelValue.data" :key="item.id" class="transition-colors">
                        <td class="px-2 py-1 border-b border-gray-100 font-medium text-gray-700">
                            {{ item.name }}
                        </td>
                        <td class="px-2 py-1 border-b border-gray-100 text-center">
                            {{ item.product?.stock }}
                        </td>
                        <td class="px-2 py-1 border-b border-gray-100 text-center">
                            <input type="checkbox" v-model="item.product.has_org_stocks"
                                @change="emits('change', modelValue)" />
                        </td>
                        <td class="px-2 py-1 border-b border-gray-100 text-center">
                            {{ locale.currencyFormat(item.product?.org_currency || currency, item.product?.org_cost) }}
                        </td>
                        <td class="px-2 py-1 border-b w-48">
                            <InputNumber v-model="item.product.price" mode="currency"
                                :currency="item?.product?.org_currency ? item.product.org_currency : item.currency"
                                :step="0.25" :showButtons="true" inputClass="w-full text-xs"
                                @input="emits('change', modelValue)" />
                            <small v-if="form?.errors[`shop_products.${item.id}.price`]"
                                class="text-red-500 flex items-center gap-1">
                                {{ form.errors[`shop_products.${item.id}.price`].join(", ") }}
                            </small>
                        </td>
                        <td class="px-2 py-1 border-b border-gray-100 text-center">
                            <span :class="{
                                'text-green-600 font-medium': getMargin(item) > 0,
                                'text-red-600 font-medium': getMargin(item) < 0,
                                'text-gray-500': getMargin(item) === 0,
                            }" class="whitespace-nowrap text-xs inline-block w-16">
                                {{ getMargin(item) + '%' }}
                            </span>
                        </td>

                        <!-- RRP -->
                        <td class="px-2 py-1 border-b w-48">
                            <div class="flex items-center gap-2">
                                <!-- Custom input -->
                                <InputNumber v-if="item.product?.useCustomRrp" v-model="item.product.rrp"
                                    mode="currency"
                                    :currency="item?.product?.org_currency ? item.product.org_currency : item.currency"
                                    :step="0.25" :showButtons="true" inputClass="w-full text-xs"
                                    @input="emits('change', modelValue)" />

                                <!-- Auto calculation -->
                                <span v-else class="text-gray-700 text-xs font-medium whitespace-nowrap">
                                    {{ locale.currencyFormat(
                                        item?.product?.org_currency || item.currency,
                                        Math.round(Number(item.product?.price) * 2.4)
                                    ) }}
                                </span>

                                <!-- Toggle -->
                                <button class="px-2 py-1 text-[10px] rounded border bg-gray-50 hover:bg-gray-100"
                                    @click="item.product!.useCustomRrp = !item.product?.useCustomRrp">
                                    {{ item.product?.useCustomRrp ? 'Auto' : 'Custom' }}
                                </button>
                            </div>

                            <small v-if="form?.errors[`shop_products.${item.id}.rrp`]"
                                class="text-red-500 flex items-center gap-1">
                                {{ form.errors[`shop_products.${item.id}.rrp`].join(", ") }}
                            </small>
                        </td>

                        <!-- RRP Margin -->
                        <td class="px-2 py-1 border-b border-gray-100 text-center">
                            <span :class="{
                                'text-green-600 font-medium': getRrpMargin(item) > 0,
                                'text-red-600 font-medium': getRrpMargin(item) < 0,
                                'text-gray-500': getRrpMargin(item) === 0,
                            }" class="whitespace-nowrap text-xs inline-block w-16">
                                {{ getRrpMargin(item) + '%' }}
                            </span>
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
