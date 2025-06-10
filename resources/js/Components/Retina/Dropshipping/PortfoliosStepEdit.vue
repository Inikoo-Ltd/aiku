<script setup lang="ts">
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import { trans } from 'laravel-vue-i18n'
import { get, set } from 'lodash'
import { Column, DataTable, IconField, InputIcon, InputNumber, InputText } from 'primevue'
import { onMounted, ref } from 'vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faSearch)

const props = defineProps<{
    portfolios: {}[]
    listState: { [key: string]: { [key: string]: string } }
}>()

const emits = defineEmits<{
    (e: "updateSelectedProducts", portfolio: {}, dataToSend: {}, keyToConditionicon: string ): void,
    (e: "mounted"): void
}>()


onMounted(() => {
    emits('mounted')
})


const valueTableFilter = ref({})
</script>

<template>
    <DataTable :value="portfolios" tableStyle="min-width: 50rem"
        :globalFilterFields="['code', 'name', 'category', 'price', 'description']"
        v-model:filters="valueTableFilter"
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

        <Column field="code" header="Code" style="max-width: 90px;">
            <template #body="{ data }">
                <div v-tooltip="data.code" class="truncate relative pr-2">
                    {{ data.code }}
                </div>
            </template>
        </Column>

        <Column field="category" header="Category" style="max-width: 200px;">

        </Column>

        <Column field="name" header="Name">
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

        <Column field="quantity_left" header="Stock" style="max-width: 200px;">

        </Column>

        <Column field="price" header="Price (Exc VAT)" style="max-width: 125px;">
            <template #body="{ data }">
                <div class="whitespace-nowrap relative pr-2">
                    <InputNumber
                        v-model="data.price"
                        @update:model-value="() => emits('updateSelectedProducts', data, {customer_price: data.price}, 'price')"
                        mode="currency"
                        :placeholder="data.price"
                        :currency="data.currency_code"
                        locale="en-GB"
                        fluid
                        :inputStyle="{textAlign: 'right'}"
                        disabled="true"
                    />
                    <ConditionIcon class="absolute -right-3 top-1" :state="get(listState, [data.id, 'price'], undefined)" />
                </div>
            </template>
        </Column>
        <!-- <Column field="platform_handle" header="Handled" style="max-width: 100px;">
            <template #body="{ data }">
                <div class="whitespace-nowrap relative pr-2">
                    {{ data.platform_handle ?? '-' }}
                </div>
            </template>
        </Column> -->

        <Column field="customer_price" header="Selling Price" style="max-width: 200px;">
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

        <Column field="description" header="Description">
            <template #body="{ data }">
                <div class="whitespace-nowrap relative pr-2">
                    <textarea
                        v-model="data.description"
                        class="w-full h-16 resize-none overflow-hidden text-sm text-gray-700 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent"
                        :placeholder="trans('No description')"
                        @blur="(e) => emits('updateSelectedProducts', data, {customer_description: data.description}, 'description')"
                    >
                    </textarea>
                    <ConditionIcon class="absolute -right-3 top-1" :state="get(listState, [data.id, 'description'], undefined)" />
                </div>
            </template>
        </Column>
    </DataTable>
</template>
