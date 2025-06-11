<script setup lang="ts">
import { get, defaultTo } from 'lodash-es'
import axios from "axios"
import { inject, onMounted, ref } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import PureInput from "@/Components/Pure/PureInput.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSpinnerThird, faSearch } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from "@/types/route"
import { debounce } from 'lodash-es'
import Tag from '@/Components/Tag.vue'
import { trans } from 'laravel-vue-i18n'
import TagPallet from '@/Components/TagPallet.vue'

library.add(faSpinnerThird, faSearch)

const layout = inject('layout', {})

const props = defineProps<{
    dataRoute: routeType
    saveRoute: routeType
    descriptor: {}
    beforeSubmit?: Function
    onFilterDatalist?: Function
}>()

const emits = defineEmits<{
    (e: 'onClose'): void
}>()

const dataList = ref([])
const loading = ref(false)
const form = useForm({ [props.descriptor.key]: [] })
const checkedAll = ref(false)
const tableFilter = useForm({
    search: '',
})


const closeModal = () => {
    emits('onClose')
}

// Method: Fetch data Pallet
const getData = debounce(async () => {
    loading.value = true
    try {
        const response = await axios.get(
            route(props.dataRoute.name, props.dataRoute.parameters),
            { params: { [`${props.descriptor.key}_filter[global]`]: tableFilter.search } } // Changed from { search: tableFilter.filter }
        )
        let finaldata = response.data.data
        if (props.onFilterDatalist) finaldata = props.onFilterDatalist(finaldata)
        dataList.value = finaldata
        console.log('dd', dataList.value)
        loading.value = false
    } catch (error) {
        loading.value = false
        notify({
            title: 'Failed to fetch data',
            text: error.message,
            type: "error",
        })
    }
}, 500)

const onSelectAllPallets = () => {
    const value = []
    if (!checkedAll.value) {
        dataList.value.forEach((item) => value.push(item.id))
        checkedAll.value = true
    } else checkedAll.value = false
    form[props.descriptor.key] = value
}

const onSelectPallet = (value) => {
    if (form.data()[props.descriptor.key].length > dataList.value.length && form.data()[props.descriptor.key].length != 0)
        checkedAll.value = false
    if (form.data()[props.descriptor.key].length == dataList.value.length && form.data()[props.descriptor.key].length != 0)
        checkedAll.value = true
    else checkedAll.value = false
}

// Method: Submit Add Pallet
const isAddPalletLoading = ref(false)
const onSubmitPallet = async () => {
    let eventData = form[props.descriptor.key]
    if (props.beforeSubmit) eventData = props.beforeSubmit(form[props.descriptor.key], dataList.value)
    router.post(route(props.saveRoute.name, props.saveRoute.parameters), { [props.descriptor.key]: eventData }, {
        preserveScroll: true,
        onBefore: () => {
            isAddPalletLoading.value = true
        },
        onFinish: () => {
            isAddPalletLoading.value = false
        },
        onSuccess: () => {
            form.reset(`${props.descriptor.key}`)
            checkedAll.value = false
            getData()
            closeModal()
        },
    })
}

onMounted(getData)

defineExpose({
    dataList,
    loading,
    tableFilter,
})

</script>

<template>
    <div class="mb-4 text-center text-xl font-medium">
        {{ trans('Select pallet') }}
    </div>

    <div class="flex items-center justify-between gap-x-6 mb-4">
        <div class="w-full md:w-1/4">
            <PureInput v-model="tableFilter.search" placeholder="Search" :loading="loading" :copy-button="true"
                @update:modelValue="() => getData()">
                <template #copyButton>
                    <div
                        class="flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 cursor-pointer opacity-20 hover:opacity-75 active:opacity-100">
                        <FontAwesomeIcon icon="fad fa-search" class="text-lg leading-none" aria-hidden="true" />
                    </div>
                </template>
            </PureInput>
        </div>

        <!-- Button: Add Pallet -->
        <div class="">
            <Button :style="'create'" :label="`add ${descriptor.title}`" size="l"
                :disabled="!form[props.descriptor.key].length" :key="form[props.descriptor.key].length"
                :loading="isAddPalletLoading" @click="onSubmitPallet" />
        </div>
    </div>

    <div class="h-96 overflow-auto ring-1 ring-black/5 sm:rounded-lg align-middle shadow">
        <table class="min-w-full border-separate border-spacing-0">
            <thead class="sticky top-0 z-10 bg-gray-100">
                <tr>
                    <th scope="col"
                        class="border-b border-gray-300 py-3.5 pl-4 text-left text-sm font-semibold backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                        <input type="checkbox" :checked="checkedAll" @change="onSelectAllPallets"
                            class="h-6 w-6 rounded cursor-pointer border-gray-300 hover:border-indigo-500 focus:ring-gray-600"
                            :style="{
                                color: layout.app.theme[0]
                            }" />
                    </th>

                    <th v-for="(item, index) in descriptor.column" :key="`header-${item.key}`" scope="col"
                        class="sticky top-0 z-10 border-b border-gray-300 px-3 py-3.5 text-left text-sm font-semibold backdrop-blur backdrop-filter sm:table-cell">
                        <slot :name="`head-${item.key}`" :data="{ headData: item, index: index }">
                            {{ item.label }}
                        </slot>
                    </th>
                </tr>
            </thead>

            <tbody v-if="!loading">
                <tr v-for="(pallet, index) in dataList" :key="pallet.id">
                    <td
                        :class="[index !== dataList.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 text-sm font-medium sm:pl-6 lg:pl-8']">
                        <input v-model="form[props.descriptor.key]" type="checkbox" :id="pallet.id" :value="pallet.id"
                            @change="onSelectPallet"
                            class="h-6 w-6 rounded cursor-pointer border-gray-300 hover:border-indigo-500 focus:ring-gray-600"
                            :style="{
                                color: layout.app.theme[0]
                            }" />
                    </td>

                    <td v-for="(column, columnIndex) in descriptor.column" :key="`column-${pallet.id}`"
                        :class="[index !== dataList.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap px-3 py-4 text-sm text-gray-500 sm:table-cell']">
                        <slot :name="`column-${column.key}`" :data="{ columnData: pallet, index: columnIndex }">
                            <template v-if="column.key === 'type_icon'">
                                <div class="text-center">
                                    <TagPallet :stateIcon="pallet[column.key]" />
                                </div>
                            </template>
                            <template v-else-if="column.key === 'stored_items'">
                                <div v-if="get(pallet, [column.key], []).length">
                                    <Tag v-for="item in pallet[column.key]">
                                        <template #label>
                                            {{ item.reference }} ({{ item.quantity }})
                                        </template>
                                    </Tag>
                                </div>
                                <div v-else>
                                    -
                                </div>
                            </template>
                            <template v-else>{{ defaultTo(get(pallet, [column.key]), "-") }}</template>
                        </slot>

                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="loading" class="flex justify-center items-center w-full h-64 p-12">
            <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin w-6" aria-hidden="true" />
        </div>

        <div v-if="dataList.length == 0 && !loading" class="flex justify-center items-center w-full h-64 p-12">
            No Data
        </div>
    </div>
</template>
