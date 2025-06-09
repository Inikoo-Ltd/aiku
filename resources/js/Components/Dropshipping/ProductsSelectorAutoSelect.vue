<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { inject, ref, watch, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'
import PureInput from '@/Components/Pure/PureInput.vue'
import Tag from '@/Components/Tag.vue'
import { trans } from 'laravel-vue-i18n'
import { debounce, get, set } from 'lodash'
import Pagination from '@/Components/Table/Pagination.vue'
import Image from '@/Components/Image.vue'
import { RouteParams } from '@/types/route-params'
import { routeType } from '@/types/route'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle } from "@fas"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import NumberWithButtonSave from '../NumberWithButtonSave.vue'
import ToggleSwitch from "primevue/toggleswitch"
import LoadingIcon from '../Utils/LoadingIcon.vue'
library.add(faCheckCircle)

const props = defineProps<{
    routeFetch: routeType
    isLoadingSubmit?: boolean
    isLoadingComponent?: boolean
    headLabel?: string
    submitLabel?: string
    withQuantity?: boolean
    label_result?: string
    valueToRefetch?: string
}>()


const emits = defineEmits<{
    (e: "submit", val: {}): void
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

// Method: Get a portfolio list
const queryPortfolio = ref('')
const portfoliosList = ref<Portfolio[]>([])
const portfoliosMeta = ref()
const portfoliosLinks = ref()
const getPortfoliosList = async (url?: string) => {
    // console.log('getPortfoliosList', url)
    isLoadingFetch.value = true
    try {
        const urlToFetch = url || route(props.routeFetch.name, {
            ...props.routeFetch.parameters,
            'filter[global]': queryPortfolio.value
        })
        const response = await axios.get(urlToFetch)
        portfoliosList.value = response.data.data
        portfoliosMeta.value = response?.data.meta || null
        portfoliosLinks.value = response?.data.links || null
        isLoadingFetch.value = false
    } catch (e) {
        console.error('Error', e)
        isLoadingFetch.value = false
        notify({
            title: trans("Something went wrong."),
            text: trans("Error while get the products list."),
            type: "error"
        })
    }
}
const debounceGetPortfoliosList = debounce(() => (getPortfoliosList()), 500)

const debounceEmitSubmit = debounce((item: Portfolio) => {
    emits('submit', item)
}, 500)


onMounted(()=> {
    getPortfoliosList()
})

onUnmounted(() => {
    portfoliosList.value = []
    portfoliosMeta.value = null
    portfoliosLinks.value = null
    queryPortfolio.value = ''
})

watch(() => props.valueToRefetch, (newVal, oldVal) => {
    console.log('xxx', oldVal, newVal)
    getPortfoliosList()
})
</script>

<template>
    <div>
        <slot name="header">
            <div class="mx-auto text-center text-2xl font-semibold pb-4">
                {{ headLabel ?? trans("Add products") }}
            </div>
        </slot>

        <div class="relative isolate">
            <div v-if="isLoadingSubmit" class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
                <LoadingIcon />
            </div>

            <div class="mb-2">
                <PureInput
                    v-model="queryPortfolio"
                    @update:modelValue="() => debounceGetPortfoliosList()"
                    :placeholder="trans('Input to search portfolios')"
                />
                <slot name="afterInput">
                </slot>
            </div>
            <div class="h-[500px] text-base font-normal">
                <!-- <div class="overflow-y-auto bg-gray-200 rounded h-full px-3 py-1">
                    <div class="font-semibold text-lg py-1">{{ trans("Suggestions") }}</div>
                    <div class="border-t border-gray-300 mb-1"></div>
                </div> -->
                <div class="col-span-4 pb-2 h-fit overflow-auto flex flex-col">
                    <div class="flex justify-between items-center">
                        <div class="font-semibold text-lg py-1">{{ props.label_result ?? trans("Result") }} ({{ locale?.number(portfoliosMeta?.total || 0) }})</div>
                    </div>
                    <div class="border-t border-gray-300 mb-1"></div>
                    <div class="h-[400px] overflow-auto py-2 relative">
                        <!-- Products list -->
                        <div class="grid grid-cols-3 gap-3 pb-2">
                            <template v-if="!isLoadingFetch">
                                <template v-if="portfoliosList.length > 0">
                                    <div
                                        v-for="(item, index) in portfoliosList"
                                        :key="index"
                                        class="relative h-fit rounded xcursor-pointer p-2 flex gap-x-2 border"
                                        :class="[
                                            typeof item.available_quantity !== 'undefined' && item.available_quantity < 1 ? 'bg-gray-200' : ''
                                        ]"
                                    >
                                        <slot name="product" :item="item">
                                            <Image v-if="item.image" :src="item.image" class="w-16 h-16 overflow-hidden" imageCover :alt="item.name" />
                                            <div class="flex flex-col justify-between">
                                                <div class="w-fit" xclick="() => selectProduct(item)">
                                                    <div v-if="!item.no_code" v-tooltip="trans('Code')" class="w-fit text-xs text-gray-400 italic mb-1">{{ item.code || 'no code' }}</div>
                                                    <div v-tooltip="trans('Name')" class="w-fit font-semibold leading-none mb-1">{{ item.name || 'no name' }}</div>
                                                    <div v-tooltip="trans('Available stock')" class="w-fit text-xs xtext-gray-400 italic mb-1">{{ trans("Stock") }}: {{ locale.number(item.available_quantity || 0) }} {{ trans("available") }}</div>
                                                    <div v-if="item.reference" v-tooltip="trans('Reference')" class="w-fit text-xs text-gray-400 italic">{{ item.reference || 'no reference' }}</div>
                                                    <div v-if="item.gross_weight" v-tooltip="trans('Weight')" class="w-fit text-xs text-gray-400 italic">{{ item.gross_weight }}</div>
                                                </div>

                                                <div v-if="!item.no_price" xclick="() => selectProduct(item)" v-tooltip="trans('Price')" class="mb-2 w-fit text-xs text-gray-x500">
                                                    {{ locale?.currencyFormat(item.currency_code || 'usd', item.price || 0) }}
                                                </div>

                                                <NumberWithButtonSave
                                                    v-if="withQuantity && (item?.available_quantity && item?.available_quantity > 0)"
                                                    :modelValue="get(item, 'quantity_ordered', 0)"
                                                    :bindToTarget="{
                                                        min: 0,
                                                        max: item?.available_quantity
                                                    }"
                                                    @update:modelValue="(e: number) => (
                                                        // Put auto select action here
                                                        set(item, 'quantity_selected', e),
                                                        debounceEmitSubmit(item)
                                                    )"
                                                    allowZero
                                                    noUndoButton
                                                    noSaveButton
                                                    parentClass="w-min"
                                                />

                                                <div v-if="typeof item.available_quantity !== 'undefined' && item.available_quantity < 1">
                                                    <Tag label="Out of stock" no-hover-color :theme="7" size="xxs" />
                                                </div>
                                            </div>
                                        </slot>
                                    </div>
                                </template>
                                <div v-else class="text-center text-gray-500 col-span-3">
                                    {{ trans("No products found") }}
                                </div>
                            </template>
                            <div
                                v-else
                                v-for="(item, index) in 6"
                                :key="index"
                                class="rounded cursor-pointer w-full h-20 flex gap-x-2 border skeleton"
                            >
                            </div>
                        </div>
                        <!-- Pagination -->
                        <Pagination
                            v-if="portfoliosMeta"
                            :on-click="getPortfoliosList"
                            :has-data="true"
                            :meta="portfoliosMeta"
                            xexportLinks="queryBuilderProps.exportLinks"
                            :per-page-options="[]"
                            xon-per-page-change="onPerPageChange"
                        />
                    </div>
                    <div class="mt-4">
                        <!-- <Button
                            xclick="() => emits('submit', selectedProduct)"
                            xdisabled="selectedProduct.length < 1"
                            xv-tooltip="selectedProduct.length < 1 ? trans('Select at least one product') : ''"
                            xlabel="submitLabel ?? `${trans('Add')} ${selectedProduct.length}`"
                            type="primary"
                            full
                            icon="fas fa-plus"
                            xloading="isLoadingSubmit"
                        /> -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
