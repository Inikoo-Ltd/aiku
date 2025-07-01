<script setup lang="ts">
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import { trans } from 'laravel-vue-i18n'
import { debounce, get, set } from 'lodash-es'
import { Checkbox, Column, ColumnGroup, DataTable, IconField, InputIcon, InputNumber, InputText, RadioButton, Row } from 'primevue'
import { inject, onMounted, ref, watch } from 'vue'

import Editor from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EditorContent } from '@tiptap/vue-3'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch, faText } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from '@/Components/Image.vue'
import { useTruncate } from '@/Composables/useTruncate'
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
library.add(faSearch, faText)

interface Portfolio {
    id: string
    code: string
    name: string
    category: string
    image: string
    quantity_left: number
    price: number
    price_inc_vat: number
    currency_code: string
    customer_price: number
    margin: number
    description: string
    is_inc_exc?: 'inc' | 'exc'
}

const props = defineProps<{
    portfolios: Portfolio[]
    listState: { [key: string]: { [key: string]: string } }
    recentlyUpdatedProduct: Portfolio
}>()

const locale = inject('locale', {})

const emits = defineEmits<{
    (e: "updateSelectedProducts", portfolio: {}, dataToSend: {}, keyToConditionicon: string ): void,
    (e: "mounted"): void
}>()


onMounted(() => {
    emits('mounted')
})

// Manipulate Portfolio after update (to get updated data)
watch(() => props.recentlyUpdatedProduct, (newPorto) => {
    if (newPorto) {

        const index = props.portfolios.findIndex(
            (item) => item.id === newPorto.id
        );

        if (index !== -1) {
            console.log('33333', props.portfolios[index])
            console.log('4444', newPorto)
            props.portfolios[index] = newPorto;
        }
    }
})


const valueTableFilter = ref({})

const isIncludeVat = ref(false)

const isModalDescription = ref(false)
const selectedDataToEditDescription = ref({})

const debounceUpdateDescription = debounce((description: string) => {
    emits('updateSelectedProducts', selectedDataToEditDescription.value, {customer_description: selectedDataToEditDescription.value.description}, 'customer_description')
}, 1000)


// Dont delete this
// Section: Modal Shipping
// const isModalShipping = ref(false)
// const selectedShippingToShowInModal = ref(null)
// const dummyShipping = [
//     {
//         form: '0kg',
//         to: '15kg',
//         code: 'SPP01',
//         location: 'UK Mainland',
//         expedited_tracked: '4.79'
//     },
//     {
//         form: '0kg',
//         to: '15kg',
//         code: 'SPP02',
//         location: 'UK Mainland',
//         expedited_tracked: '4.79'
//     },
//     {
//         form: '0kg',
//         to: '15kg',
//         code: 'SPP03',
//         location: 'UK Mainland',
//         expedited_tracked: '4.79'
//     },
//     {
//         form: '0kg',
//         to: '15kg',
//         code: 'SPP04',
//         location: 'UK Mainland',
//         expedited_tracked: '4.79'
//     },
//     {
//         form: '0kg',
//         to: '15kg',
//         code: 'SPP05',
//         location: 'UK Mainland',
//         expedited_tracked: '4.79'
//     }
// ]
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
                    {{ trans("Total") }}: <span class="font-bold">{{ portfolios.length }}</span>
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
                <div class="text-right">
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
                        {{ trans("Cost Price") }}
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
                        :modelValue="data.price_inc_vat"
                        aupdate:model-value="() => emits('updateSelectedProducts', data, {customer_price: data.price}, 'inc_exc_vat')"
                        mode="currency"
                        :placeholder="data.price_inc_vat"
                        :currency="data.currency_code"
                        locale="en-GB"
                        fluid
                        :inputStyle="{textAlign: 'right'}"
                        xdisabled="data.is_inc_exc !== 'inc'"
                        disabled
                        class="min-w-12"
                    />
                    <InputNumber
                        v-else
                        :modelValue="data.price"
                        aupdate:model-value="() => emits('updateSelectedProducts', data, {customer_price: data.price}, 'inc_exc_vat')"
                        mode="currency"
                        :placeholder="data.price"
                        :currency="data.currency_code"
                        locale="en-GB"
                        fluid
                        :inputStyle="{textAlign: 'right'}"
                        xdisabled="data.is_inc_exc !== 'exc'"
                        disabled
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

        <Column field="customer_price" header="RPP/Selling Price (Inc VAT)" style="max-width: 250px;">
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
                <div class="whitespace-nowrap relative pr-2 text-right">
                    {{ data.margin }}%
                </div>
            </template>
        </Column>

        <!-- Dont delete this -->
        <!-- <Column field="shipping" header="Shipping (Exc VAT)">
            <template #body="{ data }">
                <div @click="isModalShipping = true, selectedShippingToShowInModal = data" class="text-gray-400 hover:text-gray-600 cursor-pointer hover:bg-gray-100 rounded flex justify-center py-1 items-center gap-x-1">
                    <FontAwesomeIcon class="text-blue-500" icon="fal fa-box" fixed-width aria-hidden="true" />
                    Show
                </div>
            </template>
        </Column> -->

        <Column field="description" header="Description">
            <template #body="{ data }">
                <Button
                    type="tertiary"
                    icon="fal fa-text"
                    size="xs"
                    label="Edit description"
                    @click="() => {
                        isModalDescription = true
                        selectedDataToEditDescription = data
                    }"
                />
            </template>
        </Column>
    </DataTable>

    <!-- Modal: Description (edit) -->
    <Modal :isOpen="isModalDescription" @onClose="isModalDescription = false" closeButton :isClosableInBackground="false" width="w-full max-w-3xl max-h-[85vh]">
        <div>
            <div class=" text-lg text-center mb-4">
                Edit Description for <span class="font-semibold">{{ selectedDataToEditDescription.code }}</span>
            </div>

            <div class="relative">
                <Editor
                    v-model="selectedDataToEditDescription.description"
                    @update:modelValue="() => debounceUpdateDescription(selectedDataToEditDescription.description)"
                    :toogle="[
                        'heading', 'fontSize', 'bold', 'italic', 'underline', 'bulletList', 'fontFamily',
                        'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight', 
                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                    ]"
                >
                    <template #editor-content="{ editor }">
                        <div class="border-2 border-gray-300 rounded-lg p-3 shadow-sm focus-within:border-gray-400"
                            :class="get(listState, [selectedDataToEditDescription?.id, 'customer_description'], undefined) === 'error' ? 'errorShake' : ''"
                        >
                            <EditorContent :editor="editor" class="h-80 overflow-y-auto focus:outline-none" />
                        </div>
                    </template>
                </Editor>

                <ConditionIcon class="absolute right-3 bottom-1" :state="get(listState, [selectedDataToEditDescription.id, 'customer_description'], undefined)" />
            </div>


            <div class="mt-4">
                <Button
                    @click="() => isModalDescription = false"
                    type="tertiary"
                    label="Done"
                    full
                />
            </div>
        </div>
    </Modal>

    <!-- Dont delete this -->
    <!-- Modal: Shipping -->
    <!-- <Modal :isOpen="isModalShipping" @onClose="isModalShipping = false" closeButton :isClosableInBackground="false" width="w-full max-w-4xl max-h-[500px]">
        <div>
            <div class=" text-lg text-center mb-4">
                Shipping detail for <span class="font-semibold">{{ selectedShippingToShowInModal?.code }}</span>
            </div>

            <DataTable :value="dummyShipping" tableStyle="min-width: 50rem">
                <Column field="form" header="form"></Column>
                <Column field="to" header="to"></Column>
                <Column field="code" header="code"></Column>
                <Column field="location" header="location"></Column>
                <Column field="expedited_tracked" header="expedited_tracked"></Column>
            </DataTable>

            
            <div class="mt-4">
                <Button
                    @click="() => isModalShipping = false"
                    type="tertiary"
                    label="Close"
                    full
                />
            </div>
        </div>
    </Modal> -->

</template>
