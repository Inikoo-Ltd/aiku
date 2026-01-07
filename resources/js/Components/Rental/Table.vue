<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue"
import { faExclamationCircle, faCheckCircle, faTrash, faEdit } from "@fas"
import { faCopy } from "@fal"
import { faTrash as farTrash } from "@far"
import { faSpinnerThird } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Popover from "@/Components/Popover.vue"
import { trans } from "laravel-vue-i18n"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { get } from 'lodash-es'
import { Switch } from '@headlessui/vue'


library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy, faTrash, farTrash, faEdit)

const props = defineProps<{
    form: any
    initalForm : any
    blueprint: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
        physical_goods: {}
        rentals: {}
        services: {}
    }
}>()

const bulkData = ref([])
const bulkDiscInput = ref(0)
const currency = (inject('layout', layoutStructure))?.group?.currency


const onSelectAllRows = (input) => {
    const value = []
    if (input.target.checked) {
        props.form[props.fieldName][props.blueprint.key].forEach((item) => value.push(item.id))
    }
    bulkData.value = value
}



const onBulkDiscount = (close: Function) => {
    for (const item of props.form[props.fieldName][props.blueprint.key]) {
    
        if (bulkData.value.includes(item.id)) {
            item.percentage_off = bulkDiscInput.value
            let discountedPrice = item.price - (item.price * (bulkDiscInput.value / 100))
            item.agreed_price = discountedPrice
        }
    }

    bulkData.value = []
    close()
}

const showEdited = () => {
    const edited = []
    props.form[props.fieldName][props.blueprint.key].map((item, index) => {
        if (item.price != item.agreed_price) edited.push(item)
    })
    props.form[props.fieldName][props.blueprint.key] = edited
}

const showAll = () => {
    // Create a map of initial items keyed by their unique identifier (e.g., slug)
    const initialMap = new Map(props.fieldData[props.blueprint.key].data.map(item => [item.slug, item]));

    // Update the initial map with edited items
    props.form[props.fieldName][props.blueprint.key].forEach(item => {
        initialMap.set(item.slug, item);
    });

    // Convert the map back to an array to maintain the original order
    const edited = Array.from(initialMap.values());

    // Update the form field with the sorted array that retains the initial order
    props.form[props.fieldName][props.blueprint.key] = edited;
};



</script>

<template>

    <div class="h-9 flex  items-center justify-between mb-3">
        <!-- Button: Show all or only show edited field  -->
        <div v-if="blueprint.checkbox" class="flex items-center gap-x-3">
            <div @click="() => showEdited()"
                class="text-base leading-none font-medium cursor-pointer select-none"
                :class="props.fieldData[props.blueprint.key].data.length == props.form[props.fieldName][props.blueprint.key].length ? 'text-gray-400' : 'text-indigo-500'"
            >
                Modified
            </div>
            <Switch
                @click="() => props.fieldData[props.blueprint.key].data.length == props.form[props.fieldName][props.blueprint.key].length ? showEdited() : showAll()"
                :class="props.fieldData[props.blueprint.key].data.length == props.form[props.fieldName][props.blueprint.key].length ? '' : ''"
                class="pr-1 relative inline-flex h-5 aspect-[2/1] shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors bg-white ring-1 ring-slate-300 duration-200 shadow ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
            >
                <!-- <span class="sr-only">Use setting</span> -->
                <span aria-hidden="true" :class="props.fieldData[props.blueprint.key].data.length == props.form[props.fieldName][props.blueprint.key].length ? 'translate-x-5 bg-indigo-500' : 'translate-x-0 bg-slate-300'"
                    class="pointer-events-none inline-block h-full w-1/2 transform rounded-full  shadow-lg ring-0 transition duration-200 ease-in-out" />
            </Switch>
            <div @click="() => showAll()"
                class="text-base leading-none font-medium cursor-pointer select-none"
                :class="props.fieldData[props.blueprint.key].data.length == props.form[props.fieldName][props.blueprint.key].length ? 'text-indigo-500' : ' text-gray-400'"
            >
                Show All
            </div>
        </div>

        <Popover class="relative h-full">
            <template #button>
                <Button v-if="bulkData.length" :key="bulkData.length" label="Set all discount (%)"
                    :type="bulkData.length > 0 ? 'edit' : 'disabled'" :icon="['fal', 'list-alt']" class="" />
            </template>

            <template #content="{ close: closed }">
                <div class="w-[200px]">
                    <div class="text-xs my-2 font-medium">{{ trans('Discount (%)') }}: </div>
                    <PureInputNumber v-model="bulkDiscInput" autofocus placeholder="1-100"  :maxValue="100"
                        :suffix="true" :minValue="0" @onEnter="() => onBulkDiscount(closed)">
                        <template #suffix>
                            <div
                                class="flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 cursor-pointer hover:opacity-75 active:opacity-100 text-black">
                                %
                            </div>
                        </template>
                    </PureInputNumber>

                    <div class="flex justify-end mt-3">
                        <Button type="save" label="Set All" @click="() => onBulkDiscount(closed)" />
                    </div>
                </div>
            </template>
        </Popover>

    </div>



    <div class="-mx-4 -my-2 sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
            <div class="overflow-visible shadow ring-1 ring-black/5 sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300" :key="blueprint.key">
                    <thead class="bg-gray-50">
                        <tr>
                            <th  v-if="blueprint.checkbox" scope="col" class="px-3 py-4  pr-3 text-left text-sm font-semibold flex justify-center">
                                <input type="checkbox"
                                    class="h-6 w-6 rounded cursor-pointer border-gray-300 hover:border-indigo-500 text-indigo-600 focus:ring-gray-600"
                                    :checked="form[fieldName][blueprint.key].length && (bulkData.length == form[fieldName][blueprint.key].length)"
                                    @change="onSelectAllRows" />
                            </th>
                            <th v-for="e in props.blueprint.column" cope="col"
                                class="px-3 py-3.5 text-left text-sm font-semibold">
                                {{ e.title }}
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="(itemData, index) in form[fieldName][blueprint.key]" :key="itemData.email"
                        :class="[itemData.agreed_price == initalForm[fieldName][blueprint.key][index]['agreed_price'] ? 'bg-white' : 'bg-indigo-100']">                      
                            <!-- Column: Selector -->
                            <td  v-if="blueprint.checkbox" class="whitespace-nowrap px-3 py-4 text-sm  text-center w-20">
                                <input type="checkbox" :id="itemData.id" :value="itemData.id" v-model="bulkData"
                                    class="h-6 w-6 rounded cursor-pointer border-gray-300 hover:border-indigo-500 text-indigo-600 focus:ring-gray-600" />
                            </td>
                            <td v-for="column in props.blueprint.column" :key="column.key"
                                :class="`whitespace-nowrap px-3 py-4 text-sm ${column.class}`">

                                <div v-if="!column.type || column.type == 'text'">
                                    {{ itemData[column.key] }}
                                </div>

                                <div v-if="column.type == 'price'">
                                    {{ `${currency?.symbol} ${itemData[column.key]}` }}
                                </div>

                                <div v-else-if="column.type == 'name'">
                                    <div>
                                        <div>{{ itemData["code"] }}</div>
                                        <div class="text-xxs text-gray-400">{{ itemData["name"] }}</div>
                                    </div>
                                </div>

                                <div v-else-if="column.type == 'inputPrice'" class="w-28">
                                    <PureInputNumber v-model="itemData[column.key]" :placeholder="'Input price'" 
                                        :maxValue="itemData['price']" :prefix="true" :minValue="0" 
                                        @input="(value) => column?.propsOptions?.onChange(value, column, itemData)">
                                        <template #prefix>
                                            <div class="flex justify-center items-center pl-2">
                                                {{ `${currency?.symbol}` }}
                                            </div>
                                        </template>
                                    </PureInputNumber>
                                    <p v-if="get(form, ['errors', `${fieldName}.${blueprint.key}.${index}.${column.key}`])" class="mt-2 text-sm text-red-600">
                                        {{ get(form, ['errors', `${fieldName}.${blueprint.key}.${index}.${column.key}`]) }}
                                    </p>
                                </div>

                                <div v-else-if="column.type == 'discount'" class="w-28">
                                    <div>{{ itemData[column.key] }} %</div>
                                </div>


                                <div v-else-if="column.type == 'inputDiscount'" class="w-28">
                                    <PureInputNumber v-model="itemData[column.key]" placeholder="Input Discount" 
                                        :suffix="true" :minValue="0" :key="index" :max-value="100"
                                        @input="(value) => column?.propsOptions?.onChange(value, column, itemData)">
                                        <template #suffix>
                                            <div
                                                class="flex justify-center items-center px-2 absolute inset-y-0 right-0 text-gray-400">
                                                %
                                            </div>
                                        </template>
                                    </PureInputNumber>

                                    <p v-if="get(form, ['errors', `${fieldName}.${blueprint.key}.${index}.${column.key}`])" class="mt-2 text-sm text-red-600">
                                        {{ get(form, ['errors', `${fieldName}.${blueprint.key}.${index}.${column.key}`]) }}
                                    </p>
                                </div>

                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="form[fieldName]?.[blueprint?.key]?.length == 0">
                    <EmptyState />
                </div>
            </div>
        </div>
    </div>
</template>
