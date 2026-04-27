<script setup lang="ts">
import { faEdit, faRobot } from "@far";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
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
    price_rrp_warning_ratio: number
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
            <table class="w-full border-collapse text-xs table-fixed table-custom">
                <colgroup>
                    <col class="w-8" /> <!-- checkbox -->
                    <col class="w-12" /> <!-- shop -->
                    <col class="w-32" /> <!-- stock -->
                    <col class="w-20" /> <!-- cost -->

                    <col class="w-40" /> <!-- outer price -->
                    <col class="w-28" /> <!-- price/unit -->
                    <col class="w-20" /> <!-- margin -->

                    <col class="w-28" /> <!-- outer rrp -->
                    <col class="w-40" /> <!-- rrp/unit -->
                    <col class="w-20" /> <!-- rrp margin -->
                </colgroup>

                <thead>
                    <!-- GROUP HEADER -->
                    <tr class="text-[11px] uppercase tracking-wide text-gray-500">
                        <th colspan="4" class="border-b-0 border border-gray-400 border-b-1 border-l-2 bg-gray-200">Shop
                            Detail</th>

                        <th colspan="3"
                            class="border bg-blue-100 text-blue-700 text-center border-blue-400 border-l-2 ">
                            Price
                        </th>

                        <th colspan="3"
                            class="border border-gray-200 border-l-2 text-purple-700 border-purple-400 text-center bg-purple-100  ">
                            RRP
                        </th>
                    </tr>

                    <!-- MAIN HEADER -->
                    <tr class="text-left font-medium text-gray-600">

                        <th class="px-3 py-2 text-center border border-gray-200 border-l-2 border-gray-400 bg-gray-200">
                            <input type="checkbox" v-model="allChecked" />
                        </th>

                        <th class="px-3 py-2 border border-gray-200 border border  border-gray-400 bg-gray-200">
                            {{ trans('Shop') }}
                        </th>

                        <th class="px-3 py-2  border border-gray-200 border  border-gray-400 bg-gray-200">
                            {{ trans('Stock') }}
                        </th>

                        <th class="px-3 py-2 text-right border border-gray-200   border-gray-400 bg-gray-200">
                            {{ trans('Org Cost') }}
                        </th>

                        <!-- PRICE -->
                        <th
                            class="px-3 py-2 w-56 bg-blue-100 text-blue-700 text-right border border-blue-400 border-l-2">
                            {{ trans('Outer Price') }}
                        </th>

                        <th class="px-3 py-2 w-32 bg-blue-100 text-blue-700 text-right border border-blue-400">
                            {{ trans('Price / Unit') }}
                        </th>

                        <th class="px-3 py-2 w-20 bg-blue-100 text-blue-700 text-right border border-blue-400 border-r">
                            {{ trans('Margin') }}
                        </th>

                        <!-- RRP -->
                        <th
                            class="px-3 py-2 w-48 bg-purple-100 text-purple-700 text-right border border-purple-400 border-l-2">
                            {{ trans('Outer RRP') }}
                        </th>

                        <th class="px-3 py-2 w-32 bg-purple-100 text-purple-700 text-right border border-purple-400">
                            {{ trans('RRP / Unit') }}
                        </th>

                        <th
                            class="px-3 py-2 w-28 bg-purple-100 text-purple-700 text-right border border-purple-400 border-r-2">
                            {{ trans('RRP Margin') }}
                        </th>

                    </tr>
                </thead>

                <tr v-for="item in modelValue.data" :key="item.id"
                    class="border-b border-gray-200 hover:bg-gray-50 transition"
                    :class="{ 'opacity-50': !item.product.create_in_shop }">

                    <!-- CHECK -->
                    <td class="px-3 py-2 text-center  border-l-2 border-gray-400">
                        <input type="checkbox" v-model="item.product.create_in_shop"
                            @change="emits('change', modelValue)" />
                    </td>

                    <!-- SHOP -->
                    <td class="px-3 py-2 font-medium text-gray-700">
                        {{ item.code }}
                    </td>

                    <!-- STOCK -->
                    <td class="px-3 py-2 text-right">
                        <div class="flex flex-col items-end gap-[2px]">

                            <!-- STOCK -->
                            <div v-if="item.product?.stock" class="flex items-baseline gap-1">
                                <span class="text-sm font-semibold text-gray-800">
                                    {{ item.product?.stock }}
                                </span>
                                <span class="text-[10px] text-gray-400">
                                    {{ form.unit }}
                                </span>
                            </div>

                            <!-- VALUE -->
                            <div v-if="item.product?.org_value_in_warehouse" v-tooltip="trans('Value in warehouse')"
                                class="flex items-center gap-1 text-[11px] text-gray-500">
                                <span class="text-[10px] uppercase tracking-wide text-gray-400">
                                    warehouse value
                                </span>
                                <span class="font-medium text-gray-600">
                                    {{ Number(item.product?.org_value_in_warehouse).toLocaleString() }}
                                </span>
                            </div>

                        </div>
                    </td>

                    <!-- COST -->
                    <td class="px-3 py-2 text-right">
                        {{ locale.currencyFormat(item.product?.shop_currency ?? currency, item.product?.shop_cost) }}
                    </td>

                    <!-- PRICE BLOCK -->
                    <td class="px-3 py-2 bg-blue-50 border-l-2 border-blue-400">
                        <InputNumber v-model="item.product.price" mode="currency"
                            :disabled="!item.product.create_in_shop"
                            :currency="item?.product?.shop_currency ?? currency" :step="0.25" :showButtons="true"
                            inputClass="w-full" :min="0.01" @input="emits('change', modelValue)" />
                        <span v-if="form?.errors[`shop_products.${item.id}.price`]" class="text-xs text-red-500">{{
                            form?.errors[`shop_products.${item.id}.price`] }}</span>
                        <span v-if="item.product.org_cost
                            && item.product.price
                            && price_rrp_warning_ratio
                            && item.product.price < item.product.org_cost * (1 + price_rrp_warning_ratio / 100)"
                            class="text-xxs text-yellow-500">
                            Price should be at least {{ (item.product.org_cost * (1 + price_rrp_warning_ratio /
                                100)).toFixed(2) }} ({{ price_rrp_warning_ratio }}% above cost).
                        </span>
                    </td>

                    <td class="px-3 py-2 text-right bg-blue-50">
                        {{ locale.currencyFormat(item.product?.shop_currency ?? currency, (item.product?.price /
                            (form.trade_units.length == 1 ? parseInt(form.trade_units[0].quantity) : 1))) }}
                    </td>

                    <td class="px-3 py-2 text-right bg-blue-50 border-r border-blue-400">
                        <span :class="{
                            'text-green-600 font-medium': getMargin(item) > 0,
                            'text-red-600 font-medium': getMargin(item) < 0,
                            'text-gray-500': getMargin(item) === 0,
                        }">
                            {{ getMargin(item) + '%' }}
                        </span>
                    </td>

                    <!-- RRP BLOCK -->
                    <td class="px-3 py-2 text-right bg-purple-50 border-l-2 border-purple-400">
                        {{ locale.currencyFormat(item.product?.shop_currency ?? currency, roundDown2(
                            Number(item.product.rrp * (props.form.trade_units.length == 1 ?
                                parseInt(props.form.trade_units[0].quantity) : 1))
                        )) }}
                    </td>

                    <td class="px-3 py-2 text-right bg-purple-50">
                        <div class="flex justify-end gap-2 text-xs">
                            <div class="w-32" v-if="item.product?.useCustomRrp">
                                <InputNumber v-model="item.product.rrp" mode="currency"
                                    :disabled="!item.product.create_in_shop"
                                    :currency="item?.product?.shop_currency ?? currency" :step="0.25"
                                    :showButtons="true" inputClass="w-full" :min="0.01"
                                    @input="emits('change', modelValue)" />
                            </div>

                            <span v-else>
                                {{ locale.currencyFormat(item.product?.shop_currency ?? currency,
                                    roundDown2(Number(item.product.price / (props.form.trade_units.length == 1 ?
                                        parseInt(props.form.trade_units[0].quantity) : 1)) * 2.4)) }}
                            </span>

                            <button class="text-[10px] rounded border bg-gray-50 hover:bg-gray-100"
                                :disabled="!item.product.create_in_shop"
                                @click="item.product.useCustomRrp = !item.product.useCustomRrp">
                                <FontAwesomeIcon :icon="item.product?.useCustomRrp ? faRobot : faEdit"
                                    :title="item.product?.useCustomRrp ? 'Auto RRP' : 'Manual RRP'" />
                            </button>
                        </div>
                        <span v-if="form?.errors[`shop_products.${item.id}.rrp`]" class="text-xs text-red-500">{{
                            form?.errors[`shop_products.${item.id}.rrp`] }}</span>
                    </td>

                    <td class="px-3 py-2 text-right bg-purple-50 border-r-2 border-purple-400">
                        {{ getRrpMargin(item) + '%' }}
                    </td>

                </tr>
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

.table-custom .p-inputtext {
    font-size: 11px !important;
}
</style>