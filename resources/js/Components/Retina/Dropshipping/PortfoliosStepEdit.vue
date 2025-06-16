<script setup lang="ts">
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import { trans } from 'laravel-vue-i18n'
import { get, set } from 'lodash-es'
import { Checkbox, Column, ColumnGroup, DataTable, IconField, InputIcon, InputNumber, InputText, RadioButton, Row } from 'primevue'
import { inject, onMounted, ref } from 'vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from '@/Components/Image.vue'
import { useTruncate } from '@/Composables/useTruncate'
library.add(faSearch)

const props = defineProps<{
    portfolios: {}[]
    listState: { [key: string]: { [key: string]: string } }
}>()

const locale = inject('locale', {})

const emits = defineEmits<{
    (e: "updateSelectedProducts", portfolio: {}, dataToSend: {}, keyToConditionicon: string ): void,
    (e: "mounted"): void
}>()


onMounted(() => {
    emits('mounted')
})


const valueTableFilter = ref({})

const isIncludeVat = ref(false)
</script>

<template>
    <DataTable :value="portfolios" tableStyle="min-width: 50rem"
        :globalFilterFields="['code', 'name', 'category', 'price', 'description']"
        v-model:filters="valueTableFilter"
        removableSort
    >
        <template #header>
            <div class="flex justify-between items-center">
                <div class="text-xl">
                    Total: <span class="font-bold">{{ portfolios.length }}</span>
                </div>
                <IconField>
                    <InputIcon>
                        <FontAwesomeIcon icon="fal fa-search" class="" fixed-width aria-hidden="true" />
                    </InputIcon>
                    <InputText
                        :modelValue="get(valueTableFilter, 'global.value', '')"
                        @update:model-value="(e) => (console.log(e), set(valueTableFilter, ['global', 'value'], e))"
                        :placeholder="trans('Search in table')"
                    />
                </IconField>
            </div>
        </template>

        <Column field="image" header="Image" style="max-width: 90px;">
            <template #body="{ data }">
                <div class="w-20 h-20">
                    <Image :src="data.image" imageCover :alt="data.code" />
                </div>
            </template>
        </Column>

        <Column field="code" header="Code" style="max-width: 90px;" sortable>
            <template #body="{ data }">
                <div v-tooltip="data.code" class="truncate relative pr-2">
                    {{ data.code }}
                </div>
            </template>
        </Column>

        <Column field="category" header="Category" style="max-width: 100px;">
            <template #body="{ data }">
                <div v-tooltip="data.category" class="relative pr-2">
                    {{ useTruncate(data.category, 15) }}
                </div>
            </template>
        </Column>

        <Column field="name" header="Name" sortable removeableSort>
            <template #body="{ data }">
                <div class="whitespace-nowrap relative pr-2">
                    <textarea
                        v-model="data.name"
                        :placeholder="trans('Enter product name')"
                        class="w-full h-16 resize-none overflow-hidden text-sm text-gray-700 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent"
                        @blur="(e) => emits('updateSelectedProducts', data, {customer_product_name: data.name}, 'name')"
                    >
                    </textarea>
                    <ConditionIcon class="absolute -right-3 top-1" :state="get(listState, [data.id, 'name'], undefined)" />
                </div>
            </template>
        </Column>

        <Column field="quantity_left" header="Stock" style="max-width: 200px;" sortable>
            <template #body="{ data }">
                <div class="">
                    {{ locale.number(data.quantity_left) }}
                </div>
            </template>
        </Column>

        <!-- <ColumnGroup type="header">
            <Row>
                <Column header="Sale Rate" :colspan="4" />
            </Row>
            <Row>
                <Column header="Sales" :colspan="2" />
                <Column header="Profits" :colspan="2" />
            </Row>
            <Row>
                <Column header="Last Year" sortable field="lastYearSale" />
                <Column header="This Year" sortable field="thisYearSale" />
                <Column header="Last Year" sortable field="lastYearProfit" />
                <Column header="This Year" sortable field="thisYearProfit" />
            </Row>
        </ColumnGroup> -->

        <!-- Column: Exc VAT -->
        <Column field="price" xheader="Cost Price (Exc VAT)" style="max-width: 250px;">
            <template #header="{ column }">
                <div v-tooltip="isIncludeVat ? trans('Include VAT') : trans('Exclude VAT')">
                    <div class="font-semibold">
                        Cost Price
                    </div>

                    <div class="text-center flex items-center justify-center gap-x-1">
                        <span :class="isIncludeVat ? 'text-gray-600' : 'text-gray-400'">VAT</span>
                        <Checkbox
                            v-model="isIncludeVat"
                            binary
                        />
                    </div>
                </div>
            </template>

            <template #body="{ data }">
                <div class="whitespace-nowrap relative pr-2 flex items-center gap-x-1">
                    <InputNumber
                        v-if="isIncludeVat"
                        v-model="data.price_inc_vat"
                        @update:model-value="() => emits('updateSelectedProducts', data, {customer_price: data.price}, 'inc_exc_vat')"
                        mode="currency"
                        :placeholder="data.price_inc_vat"
                        :currency="data.currency_code"
                        locale="en-GB"
                        fluid
                        :inputStyle="{textAlign: 'right'}"
                        xdisabled="data.is_inc_exc !== 'inc'"
                        class="min-w-12"
                    />
                    <InputNumber
                        v-else
                        v-model="data.price"
                        @update:model-value="() => emits('updateSelectedProducts', data, {customer_price: data.price}, 'inc_exc_vat')"
                        mode="currency"
                        :placeholder="data.price"
                        :currency="data.currency_code"
                        locale="en-GB"
                        fluid
                        :inputStyle="{textAlign: 'right'}"
                        xdisabled="data.is_inc_exc !== 'exc'"
                        class="min-w-12"
                    />
                    <ConditionIcon class="absolute -right-3 top-1" :state="get(listState, [data.id, 'inc_exc_vat'], undefined)" />
                </div>
            </template>
        </Column>

        <!-- Column: Inc VAT -->
        <!-- <Column field="price" header="Cost Price (Inc VAT)" style="max-width: 250px;">
            <template #body="{ data }">
                <div class="whitespace-nowrap relative pr-2 flex items-center gap-x-1">                    
                    <InputNumber
                        v-model="data.price_inc_vat"
                        @update:model-value="() => emits('updateSelectedProducts', data, {customer_price: data.price}, 'inc_vat')"
                        mode="currency"
                        :placeholder="data.price_inc_vat"
                        :currency="data.currency_code"
                        locale="en-GB"
                        fluid
                        :inputStyle="{textAlign: 'right'}"
                        :disabled="data.is_inc_exc !== 'inc'"
                        class="min-w-12"
                    />
                    <ConditionIcon class="absolute -right-3 top-1" :state="get(listState, [data.id, 'inc_vat'], undefined)" />
                </div>
            </template>
        </Column> -->

        <!-- <Column field="platform_handle" header="Handled" style="max-width: 100px;">
            <template #body="{ data }">
                <div class="whitespace-nowrap relative pr-2">
                    {{ data.platform_handle ?? '-' }}
                </div>
            </template>
        </Column> -->

        <Column field="customer_price" header="Selling Price (Inc VAT)" style="max-width: 250px;">
            <template #body="{ data }">
                <div class="whitespace-nowrap relative pr-2">
                    <InputNumber
                        v-model="data.customer_price"
                        @update:model-value="() => emits('updateSelectedProducts', data, {customer_price: data.customer_price}, 'customer_price')"
                        mode="currency"
                        :placeholder="data.customer_price"
                        :currency="data.currency_code"
                        locale="en-GB"
                        fluid
                        :inputStyle="{textAlign: 'right'}"
                        :class="get(listState, [data.id, 'customer_price'], undefined) === 'error' ? 'errorShake' : ''"
                    />
                    <ConditionIcon class="absolute -right-3 top-1" :state="get(listState, [data.id, 'customer_price'], undefined)" />
                </div>
            </template>
        </Column>

        <Column field="margin" header="Profit Margin (%)" style="max-width: 125px;">
            <template #body="{ data }">
                <div class="whitespace-nowrap relative pr-2">
                    {{ data.margin }}%
                </div>
            </template>
        </Column>

        <Column field="shipping" header="Shipping (Exc VAT)">
            <template #body="{ data }">
                <FontAwesomeIcon
                    class="text-blue-500"
                    icon="fal fa-box"
                    aria-hidden="true" />
            </template>
        </Column>

        <Column field="description" header="Description">
            <template #body="{ data }">
                <FontAwesomeIcon
                    class="text-blue-500"
                    icon="fal fa-bars"
                    aria-hidden="true" />
<!--                <div class="whitespace-nowrap relative pr-2">
                    <textarea
                        v-model="data.description"
                        class="w-full h-16 resize-none overflow-hidden text-sm text-gray-700 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent"
                        :placeholder="trans('No description')"
                        @blur="(e) => emits('updateSelectedProducts', data, {customer_description: data.description}, 'description')"
                    >
                    </textarea>
                    <ConditionIcon class="absolute -right-3 top-1" :state="get(listState, [data.id, 'description'], undefined)" />
                </div>-->
            </template>
        </Column>
    </DataTable>
</template>
