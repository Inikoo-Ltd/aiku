<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { fromJSON } from "postcss";
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
        create_in_shop?: boolean;
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
        ? Number(item.product?.rrp * (props.form.trade_units.length == 1 ? parseInt(props.form.trade_units[0].quantity) : 1))
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
            modelValue.value.data.every((item) => item.product?.create_in_shop)
        );
    },
    set(val: boolean) {
        modelValue.value.data.forEach((item) => {
            if (item.product) {
                item.product.create_in_shop = val;
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
            item.product.rrp = roundDown2(Number(item.product.price / (props.form.trade_units.length == 1 ? parseInt(props.form.trade_units[0].quantity) : 1)) * 2.4);
        }

        // watcher untuk auto mode
        watch(
            () => item.product!.price,
            (newPrice) => {
                if (!item.product!.useCustomRrp) {
                    item.product!.rrp = roundDown2(Number(newPrice / (props.form.trade_units.length == 1 ? parseInt(props.form.trade_units[0].quantity) : 1)) * 2.4);
                    emits("change", modelValue.value);
                }
            },
            { immediate: true }
        );
    }
});


function roundDown2(num: number) {
    return Math.floor(num * 100) / 100;
}

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

                        <!-- Checkbox -->
                        <th class="px-2 py-1 text-center">
                            <input type="checkbox" v-model="allChecked" />
                        </th>

                        <!-- Shop (2 column width) -->
                        <th class="px-2 py-1 text-left">
                            {{ trans('Shop') }}
                        </th>

                        <!-- Stock -->
                        <th class="px-2 py-1 text-center">
                            {{ trans('Stock') }}
                        </th>

                        <!-- Org Cost -->
                        <th class="px-2 py-1 text-center">
                            {{ trans('Org cost') }}
                        </th>

                        <!-- Outer Price -->
                        <th class="px-2 py-1">
                            {{ trans('Outer Price') }}
                        </th>

                        <!-- Price per unit -->
                        <th class="px-2 py-1">
                            {{ trans('Price per unit') }}
                        </th>

                        <!-- Margin -->
                        <th class="px-2 py-1">
                            {{ trans('Margin') }}
                        </th>

                        <!-- Outer Rrp -->
                        <th class="px-2 py-1">
                            {{ trans('Outer Rrp') }}
                        </th>

                        <!-- Rrp per unit -->
                        <th class="px-2 py-1">
                            {{ trans('Rrp per unit') }}
                        </th>

                        <!-- Rrp Margin -->
                        <th class="px-2 py-1">
                            {{ trans('Rrp Margin') }}
                        </th>

                    </tr>
                </thead>

                <tbody>
                    <tr v-for="item in modelValue.data" :key="item.id" class="transition-colors"
                        :class="{ 'opacity-50': !item.product.create_in_shop }">

                        <!-- Checkbox -->
                        <td class="px-2 py-1 border-b border-gray-100 text-center">
                            <input type="checkbox" v-model="item.product.create_in_shop"
                                @change="emits('change', modelValue)" class="cursor-pointer relative z-10" />
                        </td>

                        <!-- Shop name -->
                        <td class="px-2 py-2  border-b border-gray-100 text-gray-700 font-medium col-span-2">
                            {{ item.name }}
                        </td>

                        <!-- Stock -->
                        <td class="px-2 py-2  border-b border-gray-100 text-center">
                            {{ item.product?.stock }}
                        </td>

                        <!-- Org Cost -->
                        <td class="px-2 py-2  border-b border-gray-100 text-center">
                            {{ locale.currencyFormat(item.product?.shop_currency ?? currency, item.product?.org_cost) }}
                        </td>

                        <!-- Price -->
                        <td class="px-2 py-2  border-b border-gray-100 w-32">
                            <InputNumber v-model="item.product.price" mode="currency"
                                :disabled="!item.product.create_in_shop"
                                :currency="item?.product?.shop_currency ?? item.currency ?? currency" :step="0.25"
                                :showButtons="true" inputClass="w-full text-xs" :min="0"
                                @input="emits('change', modelValue)" />
                        </td>

                        <!-- Price per unit -->
                        <td class="px-2 py-2  border-b border-gray-100 text-center">
                            {{ locale.currencyFormat(item.product?.shop_currency ?? currency, (item.product?.price / (form.trade_units.length == 1 ? parseInt(form.trade_units[0].quantity) : 1))) }}
                        </td>

                        <!-- Margin -->
                        <td class="px-2 py-2  border-b border-gray-100 text-center">
                            <span :class="{
                                'text-green-600 font-medium': getMargin(item) > 0,
                                'text-red-600 font-medium': getMargin(item) < 0,
                                'text-gray-500': getMargin(item) === 0,
                            }">
                                {{ getMargin(item) + '%' }}
                            </span>
                        </td>

                        <!-- RRP -->
                        <td class="px-2 py-2  border-b border-gray-100">
                            {{  locale.currencyFormat(item.product?.shop_currency ?? currency, roundDown2(
                                (Number(item.product.rrp) * (props.form.trade_units.length == 1 ? parseInt(props.form.trade_units[0].quantity) : 1)) * 2.4)
                            )}}
                        </td>

                        <!-- RRP per unit -->
                        <td class="px-2 py-2 border-b border-gray-100 text-center">
                            <div class="flex items-center gap-2 text-xs">
                                <div class="w-32" v-if="item.product?.useCustomRrp">
                                    <InputNumber v-model="item.product.rrp" mode="currency"
                                        :disabled="!item.product.create_in_shop"
                                        :currency="item?.product?.shop_currency ?? currency" :step="0.25"
                                        :showButtons="true" inputClass="w-full text-xs" :min="0"
                                        @input="emits('change', modelValue)" />

                                </div>

                                <span v-else>
                                    {{locale.currencyFormat(item.product?.shop_currency ?? currency, roundDown2(Number(item.product.price / (props.form.trade_units.length == 1 ? parseInt(props.form.trade_units[0].quantity) : 1)) * 2.4))}}
                                </span>

                                <button class="px-2 py-1 text-[10px] rounded border bg-gray-50 hover:bg-gray-100"
                                    :disabled="!item.product.create_in_shop"
                                    @click="item.product.useCustomRrp = !item.product.useCustomRrp">
                                    {{ item.product?.useCustomRrp ? 'Auto' : 'Custom' }}
                                </button>
                            </div>
                        </td>

                        <!-- RRP Margin -->
                        <td class="px-2 py-2  border-b border-gray-100 text-center">
                            <span :class="{
                                'text-green-600 font-medium': getRrpMargin(item) > 0,
                                'text-red-600 font-medium': getRrpMargin(item) < 0,
                                'text-gray-500': getRrpMargin(item) === 0,
                            }">
                                {{ getRrpMargin(item) + '%' }}
                            </span>
                        </td>

                    </tr>
                </tbody>

            </table>
        </div>

        <div v-else class="text-xs text-gray-500 italic p-4 text-center bg-gray-50 rounded">
            {{ trans('No data available') }}
        </div>
    </div>

</template>
<style>
div.force-xs {
    input {
        font-size: 0.75rem;
    }
}

div.grid.min-h-11 {
    div {
        min-height: 100%;
    }
}
</style>