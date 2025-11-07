<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { useLocaleStore } from "@/Stores/locale"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { computed, ref, watch, onMounted } from "vue"
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue"
import { cloneDeep, get } from 'lodash-es'
import Popover from '@/Components/Popover.vue'
import SelectQuery from '@/Components/SelectQuery.vue'
import { useForm } from "@inertiajs/vue3"

import { faExclamationTriangle } from "@fal"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faExclamationTriangle)

const props = defineProps<{
    route_pallets: routeType
    data: Array<{ label: string, location: string, quantity: number }>
}>()

const emits = defineEmits<{
    (e: 'Save', value: Array): void
}>()

const cloneData = ref(cloneDeep(props.data).map((item, index) => ({ ...item, index })))
const editable = ref(false)
const unlocatedPallet = ref(0)
const totalQuantity = ref(0)
const originalValues = ref(cloneDeep(props.data).map(item => item.quantity))
const palletForm = useForm({
    pallet: null
})

const filterOptionsStoredItems = (e) => {
    return e.filter((item) =>
        !props.data.map((storedItem) => storedItem.id).includes(item.id)
    );
};

const calculateUnlocatedPallet = () => {
    unlocatedPallet.value = originalValues.value.reduce((total, original, index) => {
        return total + (original - (cloneData.value[index]?.quantity || 0))
    }, 0)
}

const calculateTotalQuantity = () => {
    totalQuantity.value = cloneData.value.reduce((total, item) => total + item.quantity, 0)
}

watch(cloneData, () => {
    calculateUnlocatedPallet()
    calculateTotalQuantity()
}, { deep: true })

const onCancel = () => {
    editable.value = false
    cloneData.value = cloneDeep(props.data).map((item, index) => ({ ...item, index }))
    originalValues.value = cloneDeep(props.data).map(item => item.quantity)
    calculateUnlocatedPallet()
    calculateTotalQuantity()
}

const onAddRow = (closed) => {
    console.log(palletForm.pallet)
    cloneData.value.push({
        id: palletForm.pallet.id,
        reference: palletForm.pallet.reference,
        location: {
            code: palletForm.pallet.location_code,
            id: palletForm.pallet.location_id,
            slug: palletForm.pallet.location_slug
        },
        quantity: 0,
        index: cloneData.value.length
    })
    originalValues.value.push(0)
    calculateUnlocatedPallet()
    calculateTotalQuantity()
    palletForm.reset()
    closed()
}

const onDeleteRow = (index) => {
    cloneData.value.splice(index, 1)
    // Recalculate the indices to maintain uniqueness
    cloneData.value = cloneData.value.map((item, index) => ({ ...item, index }))
    calculateUnlocatedPallet()
    calculateTotalQuantity()
}

const onSave = () => {
    const finalData = []
    cloneData.value.map((item) => {
        finalData.push({
            pallet: item.id,
            location: item.location.id,
            quantity: item.quantity
        })
    })
    emits('Save', finalData)
}

onMounted(() => {
    totalQuantity.value = cloneData.value.reduce((total, item) => total + item.quantity, 0)
})

defineExpose({
    editable,
})

</script>

<template>
    <div class="flex justify-between border-b border-gray-300 p-2">
        <div class="font-semibold">{{trans("Contain Pallet")}} :</div>
        <div class="flex shrink-0 gap-3">
            <Button v-if="!editable" type="edit" size="xs" @click="editable = true" />
            <Button v-if="editable" type="tertiary" label="Cancel" size="xs" @click="onCancel" />
            <Button v-if="editable" :type="editable && unlocatedPallet == 0 ? 'save' : 'disabled'" size="xs"
                label="Save" :icon="['fas', 'fa-save']" @click="onSave" />
        </div>
    </div>
    <div v-if="editable" class="flex justify-between align-middle">
        <div>
            <div class="text-sm text-blue-500 font-medium px-2 py-2">
                {{trans("Total Quantity :")}} {{ totalQuantity }}
            </div>
            <div class="text-sm text-red-500 font-medium px-2 py-2">
                {{ trans("Unlocated Stored Items")}} : {{ unlocatedPallet }}
            </div>
        </div>
        <div class="px-2 py-4">
            <Popover position="right-10">
                <template #button>
                    <Button type="dashed" :icon='["fas", "fa-plus"]' label="Row" size="xs" />
                </template>

                <template #content="{ close: closed }">
                    <div class="w-64">
                        <SelectQuery :urlRoute="route(route_pallets.name, route_pallets.parameters)" :value="palletForm"
                            :filterOptions="filterOptionsStoredItems" :placeholder="'Select Pallet'" :required="true"
                            :trackBy="'reference'" :label="'reference'" :valueProp="'id'" :closeOnSelect="true"
                            :clearOnSearch="false" :fieldName="'pallet'" :object="true" />
                    </div>
                    <div class="py-3">
                        <Button full type="save" @click="() => onAddRow(closed)" />
                    </div>
                </template>
            </Popover>
        </div>
    </div>

    <div v-if="editable && unlocatedPallet !== 0"
        class="flex justify-start shrink-0 gap-4 bg-yellow-100 rounded-md border p-2 border-yellow-500 my-2">
        <font-awesome-icon :icon="['fal', 'exclamation-triangle']" class="text-yellow-600" />
        <span class="text-xs text-yellow-600">{{trans("You have to set all unlocated items")}}</span>
    </div>

    <div class="mt-2 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-gray-500 border border-gray-500 rounded-lg overflow-hidden">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-sm font-semibold text-start border-b border-gray-500">
                                {{ trans('Pallet') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-sm font-semibold text-start border-b border-gray-500">
                                {{ trans('Location') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-sm font-semibold text-start border-b border-gray-500">
                                {{ trans('Quantity') }}
                            </th>
                            <th v-if="editable" scope="col"
                                class="px-4 py-3 text-sm font-semibold text-end border-b border-gray-500">
                                {{ trans('Action') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-for="(pallet, index) in cloneData" :key="pallet.index" class="even:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm border-b border-gray-500">
                                {{ pallet.reference }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm border-b border-gray-500">
                                {{ pallet.location.code }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm border-b border-gray-500 w-32">
                                <span v-if="!editable">{{ useLocaleStore().number(pallet.quantity) }}</span>

                                <PureInputNumber v-else v-model="pallet.quantity"
                                    :maxValue="pallet.quantity + unlocatedPallet"
                                    :minValue="0" />
                            </td>
                            <td v-if="editable"
                                class="whitespace-nowrap px-4 py-3 text-sm text-end border-b border-gray-500 w-32">
                                <Popover v-if="pallet.quantity == 0" position="right-10">
                                    <template #button>
                                        <Button type="red" :icon='["far", "fa-trash-alt"]' />
                                    </template>

                                    <template #content="{ close: closed }">
                                        <div class="py-2 font-medium">
                                           {{trans("Are you sure you want to delete this pallet?")}}
                                        </div>
                                        <div class="flex  shrink-0 gap-2 justify-end">
                                            <Button label="No" type="tertiary" size="xs" @click="() => closed()" />
                                            <Button label="Yes" size="xs" @click="() => { onDeleteRow(index); closed(); }" />
                                        </div>
                                    </template>
                                </Popover>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
