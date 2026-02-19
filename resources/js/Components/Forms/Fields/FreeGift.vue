<script setup lang="ts">
import { inject, ref, computed, onMounted, onUnmounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from '@/Components/Elements/Buttons/Button.vue'
import axios from 'axios'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'
import PureInput from '@/Components/Pure/PureInput.vue'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import Pagination from '@/Components/Table/Pagination.vue'
import Image from '@/Components/Image.vue'
import { routeType } from '@/types/route'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle } from "@fas"
import { faPlus, faTimes, faBoxUp } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Tag from '@/Components/Tag.vue'

library.add(faCheckCircle, faTimes, faBoxUp)

const props = defineProps<{
    form: any
    fieldName: any
    options: string[] | { label?: string, value: string }[]
    fieldData: {
        fetchRoute: routeType
    }
}>()

const emits = defineEmits<{
    (e: "update:modelValue", val: Portfolio[]): void
    (e: "close"): void
    (e: "after-delete"): void
    (e: "on-select"): void
}>()

interface Portfolio {
    id: number
    name: string
    code: string
    image: any
    gross_weight: string
    price: number
    currency_code: string
    reference?: string
    no_code?: boolean
    no_price?: boolean
    is_recommended?: boolean
}

const layout = inject('layout', layoutStructure)
const locale = inject('locale', null)


const isLoadingFetch = ref(false)
const showDialog = ref(false)

const querySearch = ref('')
const list = ref<Portfolio[]>([])
const meta = ref()
const link = ref()

// const committedProducts = ref<Portfolio[]>(props.modelValue || [])

const openDialog = () => {
    // selectedProduct.value = [...committedProducts.value]
    showDialog.value = true
}

const confirmSelection = () => {
    // emits("update:modelValue", committedProducts.value)
    emits("on-select")
    showDialog.value = false
}

const getProductsList = async (url?: string) => {
    isLoadingFetch.value = true
    try {
        const tabRoute = props.fieldData.fetchRoute
        const params: Record<string, any> = { ...tabRoute.parameters }

        params['filter[global]'] = querySearch.value

        const urlToFetch = url || route(tabRoute.name, params)

        const response = await axios.get(urlToFetch)
        list.value = response.data.data
        meta.value = response?.data.meta || null
        link.value = response?.data.links || null
    } catch (e) {
        console.error('Error', e)
        notify({
            title: trans("Something went wrong."),
            text: trans("Error while getting the portfolios list."),
            type: "error"
        })
    } finally {
        isLoadingFetch.value = false
    }
}

const debouncegetProductsList = debounce(() => (getProductsList()), 500)

const compSelectedProduct = computed(() =>
    props.form[props.fieldName].map((item: Portfolio) => item.id)
)

const selectProduct = (item: Portfolio) => {
    props.form[props.fieldName]
    const exists = props.form[props.fieldName].find(p => p.id === item.id)
    if (exists) {
        props.form[props.fieldName] = props.form[props.fieldName].filter(p => p.id !== item.id)
    } else {
        props.form[props.fieldName]?.push(item)
    }
}


// Section: select all
const isAllSelected = computed(() => {
    if (list.value.length === 0) return false
    return list.value.every(item =>
        props.form[props.fieldName].some(selected => selected.id === item.id)
    )
})
const selectAllProducts = () => {
    if (isAllSelected.value) {
        const currentPageIds = list.value.map(item => item.id)
        props.form[props.fieldName] = props.form[props.fieldName].filter(
            selected => !currentPageIds.includes(selected.id)
        )
    } else {
        const currentSelectedIds = props.form[props.fieldName].map(item => item.id)
        const productsToAdd = list.value.filter(
            item => !currentSelectedIds.includes(item.id)
        )
        props.form[props.fieldName] = [...props.form[props.fieldName], ...productsToAdd]
    }
}



onMounted(() => getProductsList())
onUnmounted(() => {
    list.value = []
    meta.value = null
    link.value = null
    querySearch.value = ''
})

const clearAll = () => {
    props.form[props.fieldName] = []
}

</script>

<template>
    <div>
        <div class="">
            <div class="xflex justify-between">
                <!-- <div>
                    <h3 class="font-semibold mb-2">{{ trans(props.head_label) }}</h3>
                </div> -->
                
                <div class="flex flex-grow flex-wrap">
                    <Tag v-for="(item, index) in props.form[props.fieldName]" :key="index" class="mb-2 xflex-grow">
                        <template #label>
                            <div class="flex items-center gap-2">
                                <div>{{ item.code }} - {{ item.name }}</div>
                                <FontAwesomeIcon @click.stop="props.form[props.fieldName] = props.form[props.fieldName].filter((p: Portfolio) => p.id !== item.id)" icon="fal fa-times" fixed-width class="text-red-500 hover:text-red-700 cursor-pointer" aria-hidden="true" />
                            </div>
                        </template>
                    </Tag>
                </div>

                <div>
                    <Button
                        @click="openDialog"
                        :label="'select'"
                        size="xs"
                        full
                        type="dashed"
                        :icon="faPlus"
                    />
                </div>
            </div>
        </div>

        <!-- Dialog -->
        <Dialog v-model:visible="showDialog" modal header="Select Products"
            :style="{ width: '80vw', maxWidth: '1200px' }"
            :content-style="{ overflow: 'hidden', paddingLeft: '20px', paddingRight: '20px', }" @hide="$emit('close')">

            <div class="relative isolate">
                <!-- <div v-if="isLoadingSubmit"
                    class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
                    <LoadingIcon />
                </div> -->


                <div class="mb-2">
                    <div class="pt-2">
                        <PureInput v-model="querySearch" @update:modelValue="() => debouncegetProductsList()"
                            :placeholder="trans('Input to search')" />
                    </div>

                </div>

                <!-- list + pagination -->
                <div class="h-full  text-base font-normal">
                    <div class="col-span-4 pb-8 md:pb-2 h-fit overflow-auto flex flex-col">

                        <!-- Header -->
                        <div class="flex justify-between items-center">
                            <div class="font-semibold text-lg py-1">
                                {{ trans("Result") }} ({{ locale?.number(meta?.total || 0) }})
                            </div>
                            <div class="flex gap-2">
                                <div @click="selectAllProducts"
                                    :class="isAllSelected ? 'text-green-400' : 'cursor-pointer text-green-600 hover:text-green-700 hover:underline'">
                                    {{ trans("Select :xnumber products in this page", { xnumber: list.length }) }}
                                </div>
                                <div v-if="compSelectedProduct.length" @click="clearAll"
                                    class="cursor-pointer text-red-400 hover:text-red-600 hover:underline">
                                    {{ trans('Clear :xnumber selections', { xnumber: compSelectedProduct.length }) }}
                                    <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-300 mb-1"></div>

                        <!-- Product list -->
                        <div class="h-full md:h-[400px] overflow-auto py-2 relative">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 pb-2">
                                <template v-if="!isLoadingFetch">
                                    <template v-if="list.length > 0">
                                        <div v-for="(item, index) in list" :key="index" @click="selectProduct(item)"
                                            class="relative h-full rounded cursor-pointer p-2 flex flex-col md:flex-row gap-x-2 border"
                                            :class="[
                                                compSelectedProduct.includes(item.id)
                                                    ? 'bg-indigo-100 border-indigo-300'
                                                    : 'bg-white hover:bg-gray-200 border-transparent',
                                            ]">

                                            <FontAwesomeIcon v-if="compSelectedProduct.includes(item.id)"
                                                icon="fas fa-check-circle"
                                                class="bottom-2 right-2 absolute text-green-500" fixed-width
                                                aria-hidden="true" />

                                            <div class="w-16 h-16 border border-gray-500/20 rounded aspect-square overflow-y-clip text-xxs flex items-center justify-center">
                                                <Image v-if="item.web_images" :src="item.web_images" imageCover
                                                    :alt="item.name" />
                                                <FontAwesomeIcon v-else v-tooltip="trans('No image')"
                                                    icon="fal fa-image" class="opacity-70 text-xl" fixed-width
                                                    aria-hidden="true" />

                                            </div>

                                            <div class="flex flex-col justify-between w-full">
                                                <div>
                                                    <div v-if="!item.no_code" class="font-semibold">
                                                        {{ item.code || 'no code' }}
                                                    </div>
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <div class="leading-none mb-1">{{ item.name || 'noname' }}</div>
                                                    </div>
                                                </div>

                                                <div v-if="!item.no_price && item.price"
                                                    class="text-xs text-gray-x500">
                                                    {{ locale?.currencyFormat(item.currency_code || '', item.price || 0) }}
                                                </div>

                                                <div class="italic opacity-70 text-xs"
                                                    :class="item.available_quantity < 1 ? 'text-red-500' : ''"
                                                >
                                                    {{ item.available_quantity }} {{ trans('stocks') }}
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <div v-else class="text-center text-gray-500 col-span-3">
                                        {{ trans("No Results found") }}
                                    </div>
                                </template>
                                <div v-else v-for="(item, index) in 6" :key="index"
                                    class="rounded cursor-pointer w-full h-20 flex gap-x-2 border skeleton" />
                            </div>
                        </div>

                        <Pagination v-if="meta" :on-click="getProductsList" :has-data="true" :meta="meta"
                            :per-page-options="[]" />
                    </div>
                </div>
            </div>

            <!-- footer -->
            <template #footer>
                <Button type="secondary" @click="showDialog = false" label="Cancel"></Button>
                <Button type="create" full @click="confirmSelection" label="Select"></Button>
            </template>
        </Dialog>
    </div>
</template>
