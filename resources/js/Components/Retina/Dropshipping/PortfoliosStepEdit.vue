<script setup lang="ts">
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import { trans } from 'laravel-vue-i18n'
import { get } from 'lodash'
import { Column, DataTable, InputNumber } from 'primevue'
import { onMounted } from 'vue'

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
</script>

<template>
    <DataTable :value="portfolios" tableStyle="min-width: 50rem">
        <Column field="code" header="Code" style="max-width: 70px;">
            <template #body="{ data }">
                <div class="white relative pr-2">
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

        <Column field="price" header="price" style="max-width: 125px;">
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
                    />
                    <ConditionIcon class="absolute -right-3 top-1" :state="get(listState, [data.id, 'price'], undefined)" />
                </div>
            </template>
        </Column>

        <Column field="description" header="description">
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