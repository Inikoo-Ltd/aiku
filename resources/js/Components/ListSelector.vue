<script setup lang="ts">
import { inject, ref, computed, onMounted, onUnmounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from './Elements/Buttons/Button.vue'
import axios from 'axios'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'
import PureInput from '@/Components/Pure/PureInput.vue'
import { trans } from 'laravel-vue-i18n'
import { debounce, get, set } from 'lodash-es'
import Pagination from '@/Components/Table/Pagination.vue'
import Image from '@/Components/Image.vue'
import { routeType } from '@/types/route'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle } from "@fas"
import { faSearch, faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { faEmptySet, faTrashAlt } from '@far'

library.add(faCheckCircle, faTimes)

const props = withDefaults(defineProps<{
    modelValue?: Portfolio[]
    routeFetch: routeType
    isLoadingSubmit?: boolean
    isLoadingComponent?: boolean
    withQuantity?: boolean
    label_result?: string
    valueToRefetch?: string
    idxSubmitSuccess?: number
    key_quantity?: string
}>(), {
    key_quantity: 'quantity_selected',
})

const emits = defineEmits<{
    (e: "update:modelValue", val: Portfolio[]): void
    (e: "close"): void
}>()

interface Portfolio {
    id: number
    name: string
    code: string
    image: string
    gross_weight: string
    price: number
    currency_code: string
}

const layout = inject('layout', layoutStructure)
const locale = inject('locale', null)

const isLoadingFetch = ref(false)
const showDialog = ref(false)

const queryPortfolio = ref('')
const list = ref<Portfolio[]>([])
const meta = ref()
const link = ref()

const selectedProduct = ref<Portfolio[]>(props.modelValue || [])

// --- helpers
const done = () => {
    emits("update:modelValue", [...selectedProduct.value])
}

const getPortfoliosList = async (url?: string) => {
    isLoadingFetch.value = true
    try {
        const urlToFetch = url || route(props.routeFetch.name, {
            ...props.routeFetch.parameters,
            'filter[global]': queryPortfolio.value
        })
        const response = await axios.get(urlToFetch)
        list.value = response.data.data
        meta.value = response?.data.meta || null
        link.value = response?.data.links || null
    } catch (e) {
        console.error('Error', e)
        notify({
            title: trans("Something went wrong."),
            text: trans("Error while get the portfolios list."),
            type: "error"
        })
    } finally {
        isLoadingFetch.value = false
    }
}

const debounceGetPortfoliosList = debounce(() => (getPortfoliosList()), 500)

const compSelectedProduct = computed(() =>
    selectedProduct.value.map((item: Portfolio) => item.id)
)

const selectProduct = (item: Portfolio) => {
    const exists = selectedProduct.value.find(p => p.id === item.id)
    if (exists) {
        selectedProduct.value = selectedProduct.value.filter(p => p.id !== item.id)
    } else {
        // clone to avoid mutating original reference
        const newItem: any = { ...item }

        if (props.withQuantity && !newItem[props.key_quantity]) {
            newItem[props.key_quantity] = 1
        }

        selectedProduct.value = [...selectedProduct.value, newItem]
    }
    done()
}


const deleteProduct = (id: number) => {
    selectedProduct.value = selectedProduct.value.filter(p => p.id !== id)
    done()
}

const isAllSelected = computed(() => {
    if (list.value.length === 0) return false
    return list.value.every(item =>
        selectedProduct.value.some(selected => selected.id === item.id)
    )
})

const selectAllProducts = () => {
    if (isAllSelected.value) {
        const currentPageIds = list.value.map(item => item.id)
        selectedProduct.value = selectedProduct.value.filter(
            selected => !currentPageIds.includes(selected.id)
        )
    } else {
        const currentSelectedIds = selectedProduct.value.map(item => item.id)
        const productsToAdd = list.value.filter(
            item => !currentSelectedIds.includes(item.id)
        )
        selectedProduct.value = [...selectedProduct.value, ...productsToAdd]
    }
    done()
}

// lifecycle
onMounted(() => getPortfoliosList())
onUnmounted(() => {
    list.value = []
    meta.value = null
    link.value = null
    queryPortfolio.value = ''
})

const refetch = () => getPortfoliosList()
const clearAll = () => {
    selectedProduct.value = []
    done()
}

const resetAfterSubmit = () => {
    selectedProduct.value = []
    getPortfoliosList()
    done()
}

</script>

<template>
    <div>
        <!-- Selected list -->
        <div v-if="selectedProduct.length" class="mb-4">
            <h3 class="font-semibold mb-2">{{ trans('Selected Portfolios') }}</h3>
            <div class="border rounded-md overflow-hidden">
                <div v-for="item in selectedProduct" :key="item.id"
                    class="flex items-center justify-between gap-4 p-2 border-b last:border-b-0 bg-white hover:bg-gray-50">

                    <!-- Info -->
                    <div class="flex items-center gap-3">
                        <Image v-if="item.image" :src="item.image" class="w-12 h-12 rounded object-cover" />
                        <div>
                            <div class="font-medium leading-none">{{ item.name }}</div>
                            <div class="text-xs text-gray-500">{{ item.code || '-' }}</div>
                        </div>
                    </div>

                    <!-- Quantity + Delete -->
                    <div class="flex items-center gap-2">
                        <NumberWithButtonSave :key="item.id + '-' + (item[props.key_quantity] || 1)"
                            :modelValue="item[props.key_quantity] || 1" :bindToTarget="{ min: 1 }"
                            @update:modelValue="(val: number) => { item[props.key_quantity] = val; done() }"
                            noUndoButton noSaveButton parentClass="w-min" />
                        <button class="text-red-500 hover:text-red-700 px-4" @click="deleteProduct(item.id)">
                            <FontAwesomeIcon :icon="faTrashAlt" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="mb-4">
            <div class="border rounded-md bg-gray-50 p-6 text-center text-gray-500">
                <!-- Font Awesome Info Icon -->
                <font-awesome-icon :icon="faEmptySet" class="text-4xl text-gray-400 mb-3" />

                <p class="font-medium">No Data Available</p>
                <p class="text-sm text-gray-400 mb-4">Please add an item to see content here.</p>

            </div>
        </div>
        <!-- Trigger -->
        <Button @click="showDialog = true" :label="'Add'" full type="create"></Button>

        <!-- Dialog -->
        <Dialog v-model:visible="showDialog" modal header="Select Portfolios"
            :style="{ width: '80vw', maxWidth: '1200px' }" :content-style="{ overflow: 'hidden', padding: '20px' }"
            @hide="$emit('close')">

            <div class="relative isolate">
                <div v-if="isLoadingSubmit"
                    class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
                    <LoadingIcon />
                </div>

                <!-- search -->
                <div class="mb-2">
                    <PureInput v-model="queryPortfolio" @update:modelValue="() => debounceGetPortfoliosList()"
                        :placeholder="trans('Input to search portfolios')" />
                </div>

                <!-- list + pagination -->
                <div class="h-full md:h-[570px] text-base font-normal">
                    <div class="col-span-4 pb-8 md:pb-2 h-fit overflow-auto flex flex-col">

                        <!-- header -->
                        <div class="flex justify-between items-center">
                            <div class="font-semibold text-lg py-1">
                                {{ props.label_result ?? trans("Result") }} ({{ locale?.number(meta?.total || 0) }})
                            </div>
                            <div class="flex gap-2">
                                <div @click="selectAllProducts"
                                    :class="isAllSelected ? 'text-green-400' : 'cursor-pointer text-green-600 hover:text-green-700 hover:underline'">
                                    {{ trans("Select :number products in this page", { number: list.length }) }}
                                </div>
                                <div v-if="compSelectedProduct.length" @click="clearAll"
                                    class="cursor-pointer text-red-400 hover:text-red-600 hover:underline">
                                    {{ trans('Clear :number selections', { number: compSelectedProduct.length }) }}
                                    <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-300 mb-1"></div>

                        <!-- list -->
                        <div class="h-full md:h-[400px] overflow-auto py-2 relative">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 pb-2">
                                <template v-if="!isLoadingFetch">
                                    <template v-if="list.length > 0">
                                        <div v-for="(item, index) in list" :key="index" @click="selectProduct(item)"
                                            class="relative h-fit rounded cursor-pointer p-2 flex flex-col md:flex-row gap-x-2 border"
                                            :class="compSelectedProduct.includes(item.id)
                                                ? 'bg-indigo-100 border-indigo-300'
                                                : 'bg-white hover:bg-gray-200 border-gray-300'">

                                            <!-- check icon -->
                                            <Transition name="slide-to-right">
                                                <FontAwesomeIcon v-if="compSelectedProduct.includes(item.id)"
                                                    icon="fas fa-check-circle"
                                                    class="bottom-2 right-2 absolute text-green-500" fixed-width
                                                    aria-hidden="true" />
                                            </Transition>

                                            <slot name="product" :item="item">
                                                <Image v-if="item.image" :src="item.image"
                                                    class="w-16 h-16 overflow-hidden mx-auto md:mx-0 mb-4 md:mb-0"
                                                    imageCover :alt="item.name" />
                                                <div class="flex flex-col justify-between w-full">

                                                    <div class="flex items-center gap-2">
                                                        <div class="font-semibold leading-none mb-1">{{ item.name || 'no name' }}</div>

                                                        <!-- 🔹 Badge for recommended -->

                                                        <span v-if="item.is_recommended"
                                                            class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                                            {{ trans("Recommended") }}
                                                        </span>
                                                    </div>

                                                    <div v-if="!item.no_code" class="text-xs text-gray-400 italic">
                                                        {{ item.code || 'no code' }}
                                                    </div>
                                                    <div v-if="item.reference" class="text-xs text-gray-400 italic">
                                                        {{ item.reference }}
                                                    </div>
                                                    <div v-if="item.gross_weight" class="text-xs text-gray-400 italic">
                                                        {{ item.gross_weight }}
                                                    </div>

                                                    <div v-if="!item.no_price && item.price"
                                                        class="text-xs text-gray-x500">
                                                        {{ locale?.currencyFormat(item.currency_code || 'usd',
                                                            item.price || 0) }}
                                                    </div>

                                                    <NumberWithButtonSave v-if="withQuantity"
                                                        :modelValue="get(item, props.key_quantity, 1)"
                                                        :bindToTarget="{ min: 1 }"
                                                        @update:modelValue="(e: number) => { set(item, props.key_quantity, e); if (!selectedProduct.find(p => p.id === item.id)) selectedProduct.push(item); done() }"
                                                        noUndoButton noSaveButton parentClass="w-min" />
                                                </div>
                                            </slot>
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

                        <!-- pagination -->
                        <Pagination v-if="meta" :on-click="getPortfoliosList" :has-data="true" :meta="meta"
                            :per-page-options="[]" />
                    </div>
                </div>
            </div>

            <!-- footer -->
            <template #footer>
                <Button type="secondary" @click="showDialog = false" label="Cancel"></Button>
                <Button type="create" @click="showDialog = false" label="add"></Button>
            </template>
        </Dialog>
    </div>
</template>
