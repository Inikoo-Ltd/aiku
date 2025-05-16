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
library.add(faCheckCircle)

const props = defineProps<{
    routeFetch: routeType
    isLoadingSubmit?: boolean
    headLabel?: string
    submitLabel?: string
    withQuantity?: boolean
}>()


const emits = defineEmits<{
    (e: "submit", val: {}[]): void
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
    isLoadingFetch.value = true
    try {
        const urlToFetch = url || route(props.routeFetch.name, {
            ...props.routeFetch.parameters,
            'filter[global]': queryPortfolio.value
        })
        const response = await axios.get(urlToFetch)
        console.log('wwwwwwwwwww', response.data)
        portfoliosList.value = response.data.data
        portfoliosMeta.value = response?.data.meta || null
        portfoliosLinks.value = response?.data.links || null
        isLoadingFetch.value = false
    } catch {
        isLoadingFetch.value = false
        notify({
            title: trans("Something went wrong."),
            text: trans("Error while get the products list."),
            type: "error"
        })
    }
}
const debounceGetPortfoliosList = debounce(() => getPortfoliosList(), 500)


// Section: On select product
const selectedProduct = ref<Portfolio[]>([])
const compSelectedProduct = computed(() => {
    return selectedProduct.value?.map((item: Portfolio) => item.id)
})
const selectProduct = (item: any) => {
    const index = selectedProduct.value?.indexOf(item);
    if (index === -1) {
        selectedProduct.value?.push(item);
    } else {
        selectedProduct.value?.splice(index, 1);
    }
}

onMounted(()=> {
    getPortfoliosList()
})

onUnmounted(() => {
    portfoliosList.value = []
    portfoliosMeta.value = null
    portfoliosLinks.value = null
    queryPortfolio.value = ''
})
</script>

<template>
    <div class="">
        <div class="mx-auto text-center text-2xl font-semibold pb-4">
            {{ headLabel ?? trans("Add products") }}
        </div>
        <div class="mb-2">
            <PureInput
                v-model="queryPortfolio"
                @update:modelValue="() => debounceGetPortfoliosList()"
                :placeholder="trans('Input to search portfolios')"
            />
        </div>
        
        <div class="h-[500px] text-base font-normal">
            <!-- <div class="overflow-y-auto bg-gray-200 rounded h-full px-3 py-1">
                <div class="font-semibold text-lg py-1">{{ trans("Suggestions") }}</div>
                <div class="border-t border-gray-300 mb-1"></div>
            </div> -->

            <div class="col-span-4 pb-2 px-4 h-fit overflow-auto flex flex-col">
                <div class="flex justify-between items-center">
                    <div class="font-semibold text-lg py-1">{{ trans("Products") }} ({{ locale?.number(portfoliosMeta?.total || 0) }})</div>
                    <div v-if="compSelectedProduct.length" @click="() => selectedProduct = []" class="cursor-pointer text-red-400 hover:text-red-600">
                        {{ trans('Clear selection') }} ({{ compSelectedProduct.length }})
                        <FontAwesomeIcon :icon="faTimes" class="" fixed-width aria-hidden="true" />
                    </div>
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
                                    @click.self="() => selectProduct(item)"
                                    class="relative h-fit rounded cursor-pointer p-2 flex gap-x-2 border"
                                    :class="compSelectedProduct.includes(item.id)
                                        ? 'bg-indigo-100 border-indigo-300'
                                        : 'bg-white hover:bg-gray-200 border-gray-300'"
                                >
                                    <Transition name="slide-to-right">
                                        <FontAwesomeIcon v-if="compSelectedProduct.includes(item.id)" icon="fas fa-check-circle" class="bottom-2 right-2 absolute text-green-500" fixed-width aria-hidden="true" />
                                    </Transition>
                                    <Image :src="item.image" class="w-16 h-16 overflow-hidden" imageCover :alt="item.name" />
                                    <div class="flex flex-col justify-between">
                                        <div class="w-fit" @click="() => selectProduct(item)">
                                            <div v-tooltip="trans('Name')" class="w-fit font-semibold leading-none mb-1">{{ item.name || 'no name' }}</div>
                                            <div v-tooltip="trans('Code')" class="w-fit text-xs text-gray-400 italic">{{ item.code || 'no code' }}</div>
                                            <div v-if="item.gross_weight" v-tooltip="trans('Weight')" class="w-fit text-xs text-gray-400 italic">{{ item.gross_weight }}</div>
                                        </div>

                                        <div @click="() => selectProduct(item)" v-tooltip="trans('Price')" class="w-fit text-xs text-gray-x500">
                                            {{ locale?.currencyFormat(item.currency_code || 'usd', item.price || 0) }}
                                        </div>

                                        <NumberWithButtonSave
                                            v-if="withQuantity"
                                            :modelValue="get(item, 'quantity_selected', 1)"
                                            :bindToTarget="{ min: 1 }"
                                            @update:modelValue="(e: number) => (set(item, 'quantity_selected', e), selectedProduct.includes(item) ? '' : selectedProduct?.push(item))"
                                            noUndoButton
                                            noSaveButton
                                            parentClass="w-min"
                                        />
                                    </div>
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

                    <TransitionGroup name="list" tag="ul" class="mt-2 flex flex-wrap gap-x-2 gap-y-1">
                        <li
                            v-for="product in selectedProduct"
                            :key="product.id"
                        >
                            <Tag
                                :label="product.name"
                                closeButton
                                noHoverColor
                                @onClose="() => {
                                    selectProduct(product)
                                }"
                            />
                        </li>
                    </TransitionGroup>
                </div>
                
                <div class="mt-4">
                    <Button
                        @click="() => emits('submit', selectedProduct)"
                        :disabled="selectedProduct.length < 1"
                        v-tooltip="selectedProduct.length < 1 ? trans('Select at least one product') : ''"
                        :label="submitLabel ?? `${trans('Add')} ${selectedProduct.length} ${trans('products')}`"
                        type="primary"
                        full
                        icon="fas fa-plus"
                        :loading="isLoadingSubmit"
                    />
                    
                </div>
            </div>
        </div>
    </div>
</template>